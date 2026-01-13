Feature: AI Credentials command
  Scenario: Credentials management with WP AI Client
    Given a WP installation

    When I run `wp ai credentials list`
    Then STDOUT should contain:
      """
      No credentials configured.
      """

    When I run `wp ai credentials set openai sk-test-key`
    Then STDOUT should contain:
      """
      Success: Credentials for 'openai' saved.
      """

    When I run `wp ai credentials list`
    Then STDOUT should contain:
      """
      openai
      """

    When I run `wp ai credentials delete openai`
    Then STDOUT should contain:
      """
      Success: Credentials for 'openai' deleted.
      """
