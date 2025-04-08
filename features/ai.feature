Feature: AI command
  Scenario: Missing AI Services plugin
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
      This command currently requires the AI Services plugin.
      """

    When I run `wp plugin install ai-services --activate`
    When I try `wp ai "Hello World"`
    Then STDERR should contain:
      """
      No service satisfying the given arguments is registered and available.
      """
