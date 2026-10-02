# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial package skeleton
- `Sift::table()` public API for parsing tabular text
- `TableParser` for generic table parsing with support for tabs, pipes, and whitespace delimiters
- Laravel service provider with auto-discovery and Facade support
- Comprehensive handling for missing cells, extra cells, duplicate headers, and empty headers
- Complete test suite (72 tests) with regression cases for real-world copy-paste input
- PHPStan static analysis (Level 8) and Pint styling
