Feature: AI command
  Scenario: Missing WP AI Client
    When I try `wp ai "Hello World"`
    Then STDERR should contain:
      """
      This does not seem to be a WordPress installation.
      """

    When I try `wp ai "Hello World" --skip-wordpress`
    Then STDERR should contain:
      """
      Not implemented yet.
      """

    Given a WP installation
    When I try `wp ai "Hello World"`
    Then STDERR should contain:
      """
      This command requires the WP AI Client.
      """
