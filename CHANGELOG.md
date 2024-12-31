# Changelog

All notable changes to `laravel-api-response` will be documented in this file.

## 0.0.1 - 2023-12-07

- initial release

## 0.0.2 - 2023-12-07

- updated the readme file

## 0.0.3 - 2023-12-07

- updated composer packages

## 0.0.4 - 2023-12-07

- changed the response data key from result to data

## 0.0.5 - 2023-12-07

- fixed a typo in array response

## 0.0.6 - 2023-12-08

- added more usage information in readme file

## 0.0.7 - 2024-01-18

- updated composer packages

## 1.0.0  - 2024-03-17

- Added support for Laravel 11
- Set php8.2 as the minimum php version

## 2.0.0  - 2024-12-30

refactor: comprehensive overhaul of Laravel API Response package

Major architectural improvements and feature additions:
- Implement interface-based architecture with contracts for better extensibility
- Add robust response formatting with support for HATEOAS links
- Introduce new features: conditional responses, bulk operations, streams
- Add comprehensive error handling with custom error code mappings
- Implement response compression and rate limiting
- Add support for response caching and ETag/Last-Modified headers
- Improve pagination support with metadata
- Add localization support for messages
- Remove deprecated createdResponse method
- Update composer dependencies and add new dev tools

BREAKING CHANGE: Removes createdResponse method. Use successResponse instead.
