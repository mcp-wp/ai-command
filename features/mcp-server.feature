Feature: MCP server command

  Scenario: CRUD

    When I run `wp mcp server add foo "https://foo.example.com/mcp"`
    Then STDOUT should contain:
      """
      Server added.
      """

    When I run `wp mcp server add bar "https://bar.example.com/mcp"`
    And I run `wp mcp server add baz "https://baz.example.com/mcp"`
    And I run `wp mcp server list`
    Then STDOUT should be a table containing rows:
      | name | server | status |
      | foo | https://foo.example.com/mcp | active |
      | bar | https://bar.example.com/mcp | active |
      | baz | https://baz.example.com/mcp | active |

    When I run `wp mcp server remove bar baz`
    And I run `wp mcp server list`
    Then STDOUT should contain:
      """
      foo.example.com
      """
    And STDOUT should not contain:
      """
      bar.example.com
      """
    And STDOUT should not contain:
      """
      baz.example.com
      """

    When I try `wp mcp server add foo "https://foo.example.com/mcp"`
    Then STDERR should contain:
      """
      Server already exists.
      """

    When I run `wp mcp server add bar "https://bar.example.com/mcp"`
    And I run `wp mcp server add baz "https://baz.example.com/mcp"`
    And I run `wp mcp server update bar --status=inactive`
    Then STDOUT should contain:
      """
      Server updated.
      """

    When I run `wp mcp server list --status=inactive`
    Then STDOUT should be a table containing rows:
      | name | server | status |
      | baz | https://baz.example.com/mcp | active |
    And STDOUT should not contain:
      """
      foo.example.com
      """
    And STDOUT should not contain:
      """
      baz.example.com
      """
