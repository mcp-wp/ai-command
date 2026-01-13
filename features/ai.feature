Feature: AI command
  Scenario: AI prompt requires WordPress
    When I try `wp ai prompt "Hello World"`
    Then STDERR should contain:
      """
      This does not seem to be a WordPress installation.
      """

  Scenario: Skip WordPress not implemented
    When I try `wp ai prompt "Hello World" --skip-wordpress`
    Then STDERR should contain:
      """
      Not implemented yet.
      """

  Scenario: AI prompt requires configured models
    Given a WP installation
    When I try `wp ai prompt "Hello World"`
    Then STDERR should contain:
      """
      No models found
      """
