# WP-CLI AI Command Documentation

## Introduction

This documentation aims to explain usage, as well as present the technical specification for `ai` WP-CLI command.

Table of contents:
- [Reference](reference.md)
- [Tools](tools.md)
- [Cookbook](prompts-cookbook.md)

### Dependencies

- [WordPress](https://wordpress.org/download/)
- [WP-CLI](https://make.wordpress.org/cli/handbook/guides/installing/)
- [AI Services](https://github.com/felixarntz/ai-services/) plugin by [Felix Arntz](https://github.com/felixarntz).

## Problem

WordPress development workflows currently lack seamless integration with AI capabilities, particularly during local development. While REST API endpoints enable AI interactions with live sites, developers working with local WordPress installations have limited options for AI-assisted content creation and site management. This project aims to bridge this gap by implementing the Model Context Protocol (MCP) in the WordPress ecosystem, specifically integrating it with WP-CLI.

## Key features

- The foundation for AI-powered WordPress usage and development
- Make it easy to integrate MCP Server to LLM providers (Claude, Cursor etc)
- Integrate MCP Server in to Core, MCP Client in to WP-CLI


