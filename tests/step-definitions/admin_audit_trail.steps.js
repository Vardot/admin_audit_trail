'use strict';

/**
 * @file
 * Custom step definitions for the Admin Audit Trail module test suite.
 *
 * Mirrors the sibling Webship modules (webshare / webpage / webblog / webseo):
 * every step drives the site through the browser only - no Drush, no shell.
 * The audit log only records events triggered through a real web request
 * (admin_audit_trail_insert() returns early under PHP_SAPI === 'cli'), so the
 * scenarios provision users and content through Drupal's admin forms and then
 * assert the resulting `insert` / `update` / `delete` rows appear in the
 * Views-based report at /admin/reports/audit-trail.
 *
 * Navigation and waiting reuse webship-js's own helpers - gotoUrl (friendly
 * navigation errors) and waitForPageLoad (smart-settle: DOM ready, network
 * idle, no pending AJAX/timers) - instead of raw Playwright waits, and
 * failures are wrapped with friendly().
 */

const { Given, Then, When } = require('@cucumber/cucumber');
const path = require('path');
const {
  friendly,
  gotoUrl,
  waitForPageLoad,
} = require('webship-js/tests/step-definitions/webship');

/** Slugify a label into a Drupal machine name. */
function machineName(label) {
  return label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 32) || 'aat_test';
}

/**
 * A short identifier unique to this process run.
 *
 * Entities keyed by a unique machine name / username / path (roles,
 * vocabularies, menus, entity queues, redirects, user accounts) would clash
 * on a second run against the same database, so the create step would fail
 * silently and the (now-buried) prior row would no longer be newest. Suffixing
 * those identifiers keeps every run idempotent and the fresh event newest.
 */
let __aatSeq = 0;
function uniq() {
  __aatSeq += 1;
  return Date.now().toString(36).slice(-4) + __aatSeq;
}

/**
 * Run a step body and rethrow any failure as a tester-friendly error.
 *
 * @param {Function} body  - async function performing the step.
 * @param {string} message - human-readable description for failures.
 */
async function attempt(body, message) {
  try {
    await body();
  } catch (err) {
    throw friendly(message, err);
  }
}

/**
 * Log in as a named test user defined in cucumber.js worldParameters.users.
 *
 * The Webmaster row is the site-install super-admin. Every other row is
 * provisioned by `Given I add testing users` (see below).
 *
 * Example #1: Given I am a logged in user with the "Webmaster" user
 * Example #2: Given I am a logged in user with the "Content editor" user
 * Example #3: Given I am a logged in user with the "Authenticated user" user
 */
Given(/^I am a logged in user with( the)*( username)* "([^"]*)?"( user)?$/, async function (theCase, usernameCase, key, userCase) {
  const users = this.parameters.users || {};
  if (!(key in users)) {
    throw new Error(`No user named "${key}" in cucumber.js worldParameters.users`);
  }
  const { username, password } = users[key];
  if (!username || !password) {
    throw new Error(`User "${key}" is missing username or password in worldParameters.users`);
  }
  await attempt(async () => {
    await this.context.clearCookies();
    await gotoUrl(this.page, `${this.parameters.launchUrl}/user/login`);
    // Use Drupal's stable field IDs so the step is theme-independent.
    await this.page.locator('#edit-name').fill(username);
    await this.page.locator('#edit-pass').fill(password);
    await this.page.locator('input[value="Log in"]').click();
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not log in as "${key}"`);
});

/**
 * Provision every non-admin user from cucumber.js worldParameters.users via
 * Drupal's /admin/people/create form. Entries flagged isAdmin: true are
 * skipped (the site-install Webmaster already exists). Idempotent - skips a
 * username that already exists. Each successful creation produces a
 * `user / insert` audit event.
 *
 * Example #1: Given I add testing users
 * Example #2: And I add the testing users
 */
Given(/^(?:I |we )?add( the)? testing users$/, async function (theCase) {
  const users = this.parameters.users || {};
  await attempt(async () => {
    for (const [key, info] of Object.entries(users)) {
      if (info.isAdmin) continue;
      await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/people/create`);
      await this.page.evaluate((info) => {
        const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
        set('#edit-name', info.username);
        set('#edit-mail', info.email || `${info.username}@example.test`);
        set('#edit-pass-pass1', info.password);
        set('#edit-pass-pass2', info.password);
        for (const role of info.roles || []) {
          const cb = document.querySelector(`input[name="roles[${role}]"]`);
          if (cb) cb.checked = true;
        }
      }, info);
      await this.page.evaluate(() => document.querySelector('#edit-submit').click());
      await waitForPageLoad(this.page);
    }
  }, 'Could not provision the testing users');
});

/**
 * Create an Article node through the node-add form. The created node URL is
 * stashed on the world so a later "update" / "delete" step can target the
 * same node. Produces a `node / insert` audit event.
 *
 * Example #1: When I create an article titled "My title"
 * Example #2: Given I create an article titled "Audit Trail Article"
 */
Given(/^(?:I |we )?create an article titled "([^"]*)"$/, async function (title) {
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/node/add/article`);
    await this.page.evaluate((t) => {
      const el = document.querySelector('#edit-title-0-value');
      if (el) el.value = t;
    }, title);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
    // Remember the canonical node URL (…/node/<nid>) for follow-up edits.
    this.auditArticleUrl = this.page.url().split('?')[0];
  }, `Could not create an article titled "${title}"`);
});

/**
 * Edit the most recently created article and save it with a new title.
 * Produces a `node / update` audit event.
 *
 * Example: When I update the article I created with the title "New title"
 */
When(/^(?:I |we )?update the article I created with the title "([^"]*)"$/, async function (title) {
  await attempt(async () => {
    if (!this.auditArticleUrl) {
      throw new Error('No article has been created yet in this scenario.');
    }
    await gotoUrl(this.page, `${this.auditArticleUrl}/edit`);
    await this.page.evaluate((t) => {
      const el = document.querySelector('#edit-title-0-value');
      if (el) el.value = t;
    }, title);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not update the article to "${title}"`);
});

/**
 * Delete the most recently created article through the delete-confirm form.
 * Produces a `node / delete` audit event.
 *
 * Example: When I delete the article I created
 */
When(/^(?:I |we )?delete the article I created$/, async function () {
  await attempt(async () => {
    if (!this.auditArticleUrl) {
      throw new Error('No article has been created yet in this scenario.');
    }
    await gotoUrl(this.page, `${this.auditArticleUrl}/delete`);
    await this.page.evaluate(() => {
      const btn = document.querySelector('#edit-submit')
        || [...document.querySelectorAll('input[type="submit"], button')]
          .find(b => (b.value || b.textContent || '').trim() === 'Delete');
      if (btn) btn.click();
    });
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, 'Could not delete the created article');
});

/**
 * Filter the audit report by a single event type through the exposed filter,
 * then apply. Reloads the report at /admin/reports/audit-trail first so the
 * step is independent of the current page.
 *
 * Example: When I filter the audit report by the "Node" type
 */
When(/^(?:I |we )?filter the audit report by the "([^"]*)" type$/, async function (label) {
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/reports/audit-trail`);
    await waitForPageLoad(this.page);
    await this.page.selectOption("form.views-exposed-form select[name='type[]']", { label });
    await this.page.evaluate(() => {
      const btn = document.querySelector("form.views-exposed-form input[type='submit'], form.views-exposed-form button[type='submit']");
      if (btn) btn.click();
    });
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not filter the audit report by the "${label}" type`);
});

/* -------------------------------------------------------------------------
 * Generic named-selector assertions (shared phrasing across Webship modules).
 * ---------------------------------------------------------------------- */

/**
 * Resolve a webship-js named selector from the world registry, suggesting the
 * closest registered name on a typo instead of dumping the whole catalogue.
 */
function resolveName(world, name) {
  const css = world.__selectorsCss || {};
  const key = name.trim();
  if (Object.prototype.hasOwnProperty.call(css, key)) {
    return css[key];
  }
  const keys = Object.keys(css);
  let best = null;
  let bestDistance = Infinity;
  for (const candidate of keys) {
    const distance = (function lev(a, b) {
      const m = a.length;
      const n = b.length;
      if (!m) return n;
      if (!n) return m;
      const row = new Array(n + 1);
      for (let j = 0; j <= n; j += 1) row[j] = j;
      for (let i = 1; i <= m; i += 1) {
        let prev = i;
        for (let j = 1; j <= n; j += 1) {
          const cost = a.charCodeAt(i - 1) === b.charCodeAt(j - 1) ? 0 : 1;
          const cur = Math.min(row[j] + 1, prev + 1, row[j - 1] + cost);
          row[j - 1] = prev;
          prev = cur;
        }
        row[n] = prev;
      }
      return row[n];
    }(key, candidate));
    if (distance < bestDistance) {
      bestDistance = distance;
      best = candidate;
    }
  }
  const hint = best && bestDistance <= Math.max(4, Math.floor(key.length / 3))
    ? ` Did you mean "${best}"?`
    : '';
  throw new Error(`Unknown named selector "${key}".${hint} Run "Then print css selectors" to see all ${keys.length} registered names.`);
}

/**
 * Assert a named selector is visible / hidden / attached / focused / enabled /
 * disabled / editable.
 *
 * Example #1: Then the "audit report" element should be visible
 * Example #2: Then the "audit exposed form" element should be visible within 5 seconds
 */
Then(/^the "([^"]*)" element should be (visible|hidden|attached|focused|enabled|disabled|editable)(?: within (\d+) seconds?)?$/, async function (name, state, sec) {
  const sel = resolveName(this, name);
  const loc = this.page.locator(sel);
  const timeout = sec ? Number(sec) * 1000 : 10000;
  await attempt(async () => {
    if (state === 'visible' || state === 'attached') {
      await loc.first().waitFor({ state, timeout });
    }
    else if (state === 'hidden') {
      await loc.first().waitFor({ state: 'hidden', timeout });
    }
    else if (state === 'focused') {
      await this.page.waitForFunction(s => document.activeElement && document.activeElement.matches(s), sel, { timeout });
    }
    else {
      const fn = { enabled: 'isEnabled', disabled: 'isDisabled', editable: 'isEditable' }[state];
      const ok = await loc.first()[fn]();
      if (!ok) throw new Error(`"${name}" not ${state}`);
    }
  }, `Expected "${name}" (${sel}) to be ${state}`);
});

/**
 * Assert the count of elements matching a named selector.
 *
 * Example #1: Then the "audit report rows" element should have a count of 2
 * Example #2: Then the "audit row node insert" element should have a count of 1
 * Example #3: Then the "audit row user" element should have a count of 0
 */
Then(/^the "([^"]*)" element should have a count of (\d+)(?: within (\d+) seconds?)?$/, async function (name, expected, sec) {
  const sel = resolveName(this, name);
  const target = Number(expected);
  const timeout = sec ? Number(sec) * 1000 : 10000;
  const loc = this.page.locator(sel);
  const deadline = Date.now() + timeout;
  let last = -1;
  await attempt(async () => {
    while (Date.now() < deadline) {
      last = await loc.count();
      if (last === target) return;
      await this.page.waitForTimeout(100);
    }
    throw new Error(`count was ${last}`);
  }, `Expected "${name}" (${sel}) count to be ${target}`);
});

/**
 * Assert at least N elements match a named selector (useful for "the report
 * has rows" without pinning the exact total).
 *
 * Example: Then the "audit report rows" element should have at least a count of 1
 */
Then(/^the "([^"]*)" element should have at least a count of (\d+)(?: within (\d+) seconds?)?$/, async function (name, expected, sec) {
  const sel = resolveName(this, name);
  const target = Number(expected);
  const timeout = sec ? Number(sec) * 1000 : 10000;
  const loc = this.page.locator(sel);
  const deadline = Date.now() + timeout;
  let last = -1;
  await attempt(async () => {
    while (Date.now() < deadline) {
      last = await loc.count();
      if (last >= target) return;
      await this.page.waitForTimeout(100);
    }
    throw new Error(`count was ${last}`);
  }, `Expected "${name}" (${sel}) count to be at least ${target}`);
});

/**
 * Assert the first element matching a named selector contains the given text.
 *
 * Example #1: Then the "audit col type" element should contain text "node"
 * Example #2: Then the "drupal page heading" element should contain text "Admin Audit Trail"
 */
Then(/^the "([^"]*)" element should contain text "([^"]*)"(?: within (\d+) seconds?)?$/, async function (name, text, sec) {
  const sel = resolveName(this, name);
  const timeout = sec ? Number(sec) * 1000 : 10000;
  await attempt(async () => {
    await this.page.waitForFunction(
      ([s, t]) => { const el = document.querySelector(s); return el && el.textContent.includes(t); },
      [sel, text],
      { timeout, polling: 100 },
    );
  }, `Expected "${name}" (${sel}) to contain text "${text}"`);
});

/**
 * Assert an element's computed CSS value for a property.
 *
 * Generic and reusable - resolves the selector the same way as the other
 * element steps and reads window.getComputedStyle, so it works for any element
 * and any CSS property.
 *
 * Example: Then the "audit col path" element should have the computed style "overflow-wrap" of "anywhere"
 */
Then(/^the "([^"]*)" element should have the computed style "([^"]*)" of "([^"]*)"$/, async function (name, property, value) {
  const sel = resolveName(this, name);
  await attempt(async () => {
    await this.page.waitForFunction(
      ([s, p, v]) => {
        const el = document.querySelector(s);
        return !!el && window.getComputedStyle(el).getPropertyValue(p).trim() === v;
      },
      [sel, property, value],
      { timeout: 10000, polling: 100 },
    );
  }, `Expected "${name}" (${sel}) computed ${property} to be "${value}"`);
});

/**
 * Assert that the audit report lists at least one row whose Operation cell
 * contains the given text. Unlike "the 'audit col operation' element should
 * contain text", this scans EVERY operation cell, so it holds even when the
 * triggering event is not the newest row (e.g. an auth event followed by the
 * re-login needed to view the report).
 *
 * Example #1: Then the audit report should list the operation "logout"
 * Example #2: Then the audit report should list the operation "request password"
 */
Then(/^the audit report should list the operation "([^"]*)"(?: within (\d+) seconds?)?$/, async function (op, sec) {
  const timeout = sec ? Number(sec) * 1000 : 10000;
  await attempt(async () => {
    await this.page.waitForFunction(
      (op) => [...document.querySelectorAll('.view-admin-audit-trail td.views-field-operation')]
        .some(td => td.textContent.includes(op)),
      op,
      { timeout, polling: 100 },
    );
  }, `Expected the audit report to list an operation containing "${op}"`);
});

/**
 * Click the first element matching a named selector, falling back to a JS
 * .click() if Playwright actionability is blocked.
 *
 * Example: When I click the "audit legacy link" element
 */
When(/^(?:I |we )?click(?: on)?(?: the)? "([^"]*)" element$/, async function (name) {
  const sel = resolveName(this, name);
  await attempt(async () => {
    const loc = this.page.locator(sel).first();
    await loc.waitFor({ state: 'visible', timeout: 10000 });
    try {
      await loc.click({ timeout: 4000 });
    }
    catch (e) {
      await this.page.evaluate((s) => {
        const el = document.querySelector(s);
        if (el) el.click();
      }, sel);
    }
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not click the "${name}" element`);
});

/**
 * Resolve a form field locator by label.
 */
function fieldLocator(page, label) {
  return page
    .locator('label.form-item__label, label.form-required, label')
    .filter({ hasText: new RegExp(`^\\s*${label.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&')}(\\s|$)`, 'i') })
    .first();
}

/**
 * Assert that a form field with the given label is visible on the page.
 *
 * Example #1: Then I should see a "Row limit" field
 * Example #2: Then I should see an "Event type" field
 */
Then(/^(?:I |we )?should see a "([^"]*)" field$/, async function (label) {
  await attempt(async () => {
    await fieldLocator(this.page, label).waitFor({ state: 'visible', timeout: 10000 });
  }, `Expected to find a field labeled "${label}"`);
});

Then(/^(?:I |we )?should see an "([^"]*)" field$/, async function (label) {
  await attempt(async () => {
    await fieldLocator(this.page, label).waitFor({ state: 'visible', timeout: 10000 });
  }, `Expected to find a field labeled "${label}"`);
});

/**
 * Assert that a button with the given text is visible on the page.
 *
 * Example: Then I should see the button "Save configuration"
 */
Then(/^(?:I |we )?should see the button "([^"]*)"$/, async function (text) {
  await attempt(async () => {
    await this.page.getByRole('button', { name: text, exact: false }).first()
      .waitFor({ state: 'visible', timeout: 10000 });
  }, `Expected to find a button with text "${text}"`);
});

/* =======================================================================
 * Submodule event-generation steps.
 *
 * Each step performs the minimal admin-UI action that triggers one handler
 * submodule's event(s); the matching feature then opens the report and
 * asserts the resulting row (named selectors use Playwright's :has-text /
 * :text-is engine, web-first auto-wait). Forms are filled via stable Drupal
 * field IDs / data-drupal-selector attributes (which, unlike #id, survive
 * AJAX rebuilds) and submitted with a scoped op button.
 * ==================================================================== */

/* user - create a user account (logs user / insert) */
When(/^(?:I |we )?create a user named "([^"]*)"$/, async function (username) {
  const unique = `${username}_${uniq()}`;
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/people/create`);
    await this.page.evaluate((u) => {
      const set = (s, v) => { const el = document.querySelector(s); if (el) el.value = v; };
      set('#edit-name', u);
      set('#edit-mail', `${u}@example.test`);
      set('#edit-pass-pass1', 'dD.123123ddd');
      set('#edit-pass-pass2', 'dD.123123ddd');
    }, unique);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not create the user "${unique}"`);
});

/* user_roles - create a role (logs user_roles / role_created) */
When(/^(?:I |we )?create a user role named "([^"]*)"$/, async function (label) {
  const id = machineName(label) + '_' + uniq();
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/people/roles/add`);
    await this.page.evaluate(([label, id]) => {
      const set = (s, v) => { const el = document.querySelector(s); if (el) el.value = v; };
      set('#edit-label', label);
      set('#edit-id', id);
    }, [label, id]);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not create the user role "${label}"`);
});

/* taxonomy - create a vocabulary and a term */
When(/^(?:I |we )?create a taxonomy vocabulary named "([^"]*)"$/, async function (label) {
  const id = machineName(label) + '_' + uniq();
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/structure/taxonomy/add`);
    await this.page.evaluate(([label, id]) => {
      const set = (s, v) => { const el = document.querySelector(s); if (el) el.value = v; };
      set('#edit-name', label);
      set('#edit-vid', id);
    }, [label, id]);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
    // Remember the created vocabulary so a later step can delete it.
    this.createdVocabularyId = id;
  }, `Could not create the vocabulary "${label}"`);
});

/**
 * Delete the taxonomy vocabulary created earlier in the scenario.
 *
 * Example #1: When I delete the taxonomy vocabulary I created
 * Example #2: And we delete the taxonomy vocabulary I created
 */
When(/^(?:I |we )?delete the taxonomy vocabulary I created$/, async function () {
  await attempt(async () => {
    if (!this.createdVocabularyId) {
      throw new Error('No taxonomy vocabulary has been created yet in this scenario.');
    }
    await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/structure/taxonomy/manage/${this.createdVocabularyId}/delete`);
    await this.page.evaluate(() => {
      const btn = document.querySelector('input[name="op"][value="Delete"]') || document.querySelector('#edit-submit');
      if (btn) btn.click();
    });
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, 'Could not delete the taxonomy vocabulary');
});

When(/^(?:I |we )?create a taxonomy term named "([^"]*)"$/, async function (term) {
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/structure/taxonomy/manage/tags/add`);
    await this.page.evaluate((t) => {
      const el = document.querySelector('#edit-name-0-value');
      if (el) el.value = t;
    }, term);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not create the taxonomy term "${term}"`);
});

/* multilingual setup - generic, reusable language + content-translation steps */
When(/^(?:I |we )?add the "([^"]*)" language$/, async function (langcode) {
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/config/regional/language/add`);
    // Idempotent: the predefined list omits already-added languages.
    const added = await this.page.evaluate((lc) => {
      const sel = document.querySelector('#edit-predefined-langcode');
      const opt = sel && sel.querySelector(`option[value="${lc}"]`);
      if (!opt) {
        return false;
      }
      sel.value = lc;
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    }, langcode);
    if (added) {
      await this.page.evaluate(() => document.querySelector('#edit-submit').click());
      await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
    }
  }, `Could not add the "${langcode}" language`);
});

When(/^(?:I |we )?make the "([^"]*)" "([^"]*)" entity translatable$/, async function (entityType, bundle) {
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/config/regional/content-language`);
    await this.page.evaluate(([et, b]) => {
      const check = (name) => { const el = document.querySelector(`input[name="${name}"]`); if (el) el.checked = true; };
      check(`entity_types[${et}]`);
      check(`settings[${et}][${b}][translatable]`);
      // Make the bundle's fields translatable too, otherwise the translation
      // form has no editable fields and a real translation is never created.
      document.querySelectorAll(`input[name^="settings[${et}][${b}][fields]["]`).forEach((el) => { el.checked = true; });
    }, [entityType, bundle]);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not make "${entityType} / ${bundle}" translatable`);
});

/* menu - create a menu and a menu link */
When(/^(?:I |we )?create a menu named "([^"]*)"$/, async function (label) {
  const id = (machineName(label).replace(/_/g, '-').slice(0, 20) + '-' + uniq());
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/structure/menu/add`);
    await this.page.evaluate(([label, id]) => {
      const set = (s, v) => { const el = document.querySelector(s); if (el) el.value = v; };
      set('#edit-label', label);
      set('#edit-id', id);
    }, [label, id]);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
    this.auditMenuId = id;
  }, `Could not create the menu "${label}"`);
});

When(/^(?:I |we )?add a menu link titled "([^"]*)" to the menu I created$/, async function (title) {
  await attempt(async () => {
    if (!this.auditMenuId) throw new Error('No menu created yet in this scenario.');
    await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/structure/menu/manage/${this.auditMenuId}/add`);
    await this.page.evaluate((t) => {
      const set = (s, v) => { const el = document.querySelector(s); if (el) el.value = v; };
      set('#edit-title-0-value', t);
      set('#edit-link-0-uri', '/node');
    }, title);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
    // Capture the created menu link content id for later translation steps. The
    // new menu has a single link, so the first item edit link is the one added.
    this.auditMenuLinkId = await this.page.evaluate(() => {
      const a = document.querySelector('a[href*="/admin/structure/menu/item/"]');
      const m = a && a.getAttribute('href').match(/\/admin\/structure\/menu\/item\/(\d+)\//);
      return m ? m[1] : null;
    });
  }, `Could not add the menu link "${title}"`);
});

/* menu link translations - generic add/delete of a menu link translation */
When(/^(?:I |we )?add the "([^"]*)" translation titled "([^"]*)" to the menu link I created$/, async function (langcode, title) {
  await attempt(async () => {
    if (!this.auditMenuLinkId) throw new Error('No menu link created yet in this scenario.');
    const id = this.auditMenuLinkId;
    await gotoUrl(this.page, `${this.parameters.launchUrl}/${langcode}/admin/structure/menu/item/${id}/edit/translations/add/en/${langcode}`);
    await this.page.evaluate((t) => {
      const el = document.querySelector('#edit-title-0-value');
      if (el) el.value = t;
    }, title);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not add the "${langcode}" translation "${title}" to the menu link`);
});

When(/^(?:I |we )?delete the "([^"]*)" translation of the menu link I created$/, async function (langcode) {
  await attempt(async () => {
    if (!this.auditMenuLinkId) throw new Error('No menu link created yet in this scenario.');
    const id = this.auditMenuLinkId;
    await gotoUrl(this.page, `${this.parameters.launchUrl}/${langcode}/admin/structure/menu/item/${id}/delete`);
    await this.page.evaluate(() => {
      const b = document.querySelector('#edit-submit') || document.querySelector('input[name="op"]');
      if (b) b.click();
    });
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not delete the "${langcode}" translation of the menu link`);
});

/* block_content - create a custom (basic) block */
When(/^(?:I |we )?create a custom block named "([^"]*)"$/, async function (label) {
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/block/add/basic`);
    await this.page.evaluate((label) => {
      const el = document.querySelector('#edit-info-0-value');
      if (el) el.value = label;
    }, label);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not create the custom block "${label}"`);
});

/* comment - post a comment on the article created earlier in the scenario.
 * The comment body is a CKEditor5 field; set its data via the editor
 * instance (a raw textarea .value is overwritten by the editor on submit). */
When(/^(?:I |we )?post the comment "([^"]*)" on the article I created$/, async function (body) {
  await attempt(async () => {
    if (!this.auditArticleUrl) throw new Error('No article created yet in this scenario.');
    await gotoUrl(this.page, this.auditArticleUrl);
    await this.page.evaluate((body) => {
      const subj = document.querySelector('#edit-subject-0-value');
      if (subj) subj.value = 'Audit comment subject';
      const editable = document.querySelector('#comment-form .ck-editor__editable, .ck-editor__editable');
      const instance = editable && editable.ckeditorInstance;
      if (instance) instance.setData(`<p>${body}</p>`);
      else { const ta = document.querySelector('#edit-comment-body-0-value'); if (ta) ta.value = body; }
    }, body);
    await this.page.evaluate(() => {
      const form = document.querySelector('form#comment-form, form[id^="comment-form"]');
      const btn = form
        ? (form.querySelector('input[id^="edit-submit"]') || form.querySelector('input[name="op"]:not(#edit-preview)'))
        : null;
      if (btn) btn.click();
    });
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not post the comment "${body}"`);
});

/* redirect (contrib) - create a URL redirect */
When(/^(?:I |we )?create a redirect from "([^"]*)" to "([^"]*)"$/, async function (from, to) {
  const uniqSuffix = uniq();
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/config/search/redirect/add`);
    await this.page.evaluate(([from, to, suffix]) => {
      const set = (s, v) => { const el = document.querySelector(s); if (el) el.value = v; };
      set('#edit-redirect-source-0-path', from.replace(/^\//, '') + '-' + suffix);
      set('#edit-redirect-redirect-0-uri', to);
    }, [from, to, uniqSuffix]);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not create the redirect ${from} -> ${to}`);
});

/* entityqueue (contrib) - create a (simple) entity queue */
When(/^(?:I |we )?create an entity queue named "([^"]*)"$/, async function (label) {
  const id = machineName(label) + '_' + uniq();
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/admin/structure/entityqueue/add`);
    await this.page.evaluate(([label, id]) => {
      const set = (s, v) => { const el = document.querySelector(s); if (el) el.value = v; };
      set('#edit-label', label);
      set('#edit-id', id);
      const simple = document.querySelector('#edit-handler-simple');
      if (simple) simple.checked = true;
    }, [label, id]);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not create the entity queue "${label}"`);
});

/* group (contrib) - create a Team group */
When(/^(?:I |we )?create a group named "([^"]*)"$/, async function (label) {
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/group/add/team`);
    await this.page.evaluate((label) => {
      const el = document.querySelector('#edit-label-0-value');
      if (el) el.value = label;
    }, label);
    await this.page.evaluate(() => document.querySelector('#edit-submit').click());
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not create the group "${label}"`);
});

/* paragraphs (contrib) - create an article carrying a paragraph */
When(/^(?:I |we )?create an article with a paragraph titled "([^"]*)"$/, async function (title) {
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/node/add/article`);
    const addBtn = this.page.locator('[data-drupal-selector="field-sections-test-section-add-more"], #field-sections-test-section-add-more');
    const haveRow = await this.page.locator('[data-drupal-selector^="edit-field-sections-0"]').count();
    if (await addBtn.count() > 0 && haveRow === 0) {
      await addBtn.first().click();
      await waitForPageLoad(this.page);
    }
    await this.page.evaluate((t) => {
      const el = document.querySelector('[data-drupal-selector="edit-title-0-value"], #edit-title-0-value');
      if (el) el.value = t;
    }, title);
    await this.page.evaluate(() => {
      const btn = document.querySelector('[data-drupal-selector="edit-submit"]') || document.querySelector('#edit-submit');
      if (btn) btn.click();
    });
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
    this.auditArticleUrl = this.page.url().split('?')[0];
  }, `Could not create an article with a paragraph titled "${title}"`);
});

/* media + file - create an Image media item. Uploading the image creates a
 * managed file (logs file / insert); saving the media logs media / insert.
 * The Alt field id gets an AJAX suffix, so it is addressed by its stable
 * data-drupal-selector. */
When(/^(?:I |we )?create an image media item named "([^"]*)"$/, async function (name) {
  const image = path.resolve('tests/fixtures/test-image.png');
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/media/add/image`);
    await this.page.setInputFiles('input[type="file"][id^="edit-field-media-image"]', image);
    const alt = this.page.locator('[data-drupal-selector="edit-field-media-image-0-alt"]');
    await alt.waitFor({ state: 'visible', timeout: 25000 });
    await alt.fill('Audit trail media image');
    const nameField = this.page.locator('[data-drupal-selector="edit-name-0-value"]');
    if (await nameField.count() > 0) {
      await nameField.fill(name).catch(() => {});
    }
    await this.page.locator('input[type="submit"][value="Save"]').first().click();
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not create the image media item "${name}"`);
});

/* auth - logout, failed login, password-reset request */
When(/^(?:I |we )?log out$/, async function () {
  await attempt(async () => {
    await gotoUrl(this.page, `${this.parameters.launchUrl}/user/logout`);
    // Drupal 10.3+ shows a logout-confirm form. Click the "Log out" op button
    // specifically (the page also carries search blocks whose submit is
    // #edit-submit too).
    const confirm = this.page.locator('input[name="op"][value="Log out"]');
    if (await confirm.count() > 0) {
      await this.page.evaluate(() => {
        const btn = document.querySelector('input[name="op"][value="Log out"]');
        if (btn) btn.click();
      });
      await waitForPageLoad(this.page);
    }
  }, 'Could not log out');
});

When(/^(?:I |we )?attempt to log in with an incorrect password$/, async function () {
  await attempt(async () => {
    await this.context.clearCookies();
    await gotoUrl(this.page, `${this.parameters.launchUrl}/user/login`);
    await this.page.locator('#edit-name').fill('webmaster');
    await this.page.locator('#edit-pass').fill('definitely-the-wrong-password');
    await this.page.locator('input[value="Log in"]').click();
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, 'Could not submit an incorrect login');
});

When(/^(?:I |we )?request a new password for "([^"]*)"$/, async function (username) {
  await attempt(async () => {
    await this.context.clearCookies();
    await gotoUrl(this.page, `${this.parameters.launchUrl}/user/password`);
    await this.page.evaluate((u) => {
      const el = document.querySelector('#edit-name');
      if (el) el.value = u;
    }, username);
    await this.page.evaluate(() => {
      const btn = document.querySelector('form#user-pass input[name="op"]') || document.querySelector('#edit-submit');
      if (btn) btn.click();
    });
    await waitForPageLoad(this.page, this.minWaitTime && this.minWaitTime.page);
  }, `Could not request a new password for "${username}"`);
});

/**
 * Assert the trimmed text content of a named element is at most N characters.
 *
 * Generic - reusable for any element whose length must be bounded (e.g. a value
 * trimmed to a database column limit).
 *
 * Example #1: Then the "audit col path" element text should be at most 255 characters long
 * Example #2: Then the "summary" element text should be at most 100 characters long
 */
Then(/^the "([^"]*)" element text should be at most (\d+) characters long$/, async function (name, max) {
  const sel = resolveName(this, name);
  const limit = Number(max);
  await attempt(async () => {
    const text = ((await this.page.locator(sel).first().textContent()) || '').trim();
    if (text.length > limit) {
      throw new Error(`text length was ${text.length} (> ${limit})`);
    }
  }, `Expected "${name}" text to be at most ${limit} characters`);
});
