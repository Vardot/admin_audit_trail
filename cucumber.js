// Default cucumber-js config for the Admin Audit Trail webship-js suite.
//
// Runs the Drupal Standard profile feature set (Olivero front end, Claro
// admin theme) under tests/features/drupal/.
//
//   npx cucumber-js --config cucumber.js
//
// Reusable step definitions ship with webship-js; the module-specific steps
// live in tests/step-definitions/admin_audit_trail.steps.js. Artefacts land
// directly under tests/reports, tests/screenshots and tests/videos.

const baseWorldParameters = require('./cucumber.shared.js');

module.exports = {
  default: {
    timeout: 60000,
    requireModule: ['tsx/cjs'],
    require: [
      'node_modules/webship-js/tests/step-definitions/**/*.js',
      'tests/step-definitions/**/*.js',
    ],
    paths: ['tests/features/drupal/**/*.feature'],
    format: [
      '@cucumber/pretty-formatter',
      'json:tests/reports/cucumber_report.json',
    ],
    worldParameters: baseWorldParameters,
  },
};
