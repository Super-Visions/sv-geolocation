# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased
### Added
- RAW coordinate can now also be changed in edit mode.

## 1.9.1 - 2023-03-27
### Fixed
- Crash notification param when no geolocation is set.

## 1.9.0  - 2023-01-31
### Added
- Interactive Google Maps now respect user language.

### Fixed
- Interactive Google Maps API warns about required callback parameter.
- Interactive Google Maps blocked the edit page 'cancel' button.

## 1.8.1 - 2023-01-19
### Fixed
- PHP TypeError when no coordinates are set.

## 1.8.0 - 2022-10-27
### Added
- Improved support with [Location hierarchy](https://www.itophub.io/wiki/page?id=extensions:combodo-location-hierarchy) extension.
- Create `ormGeolocation` from string.
- Added functions for different notification placeholder representation.

### Changed
- Improved code documentation and code cleanup.

## 1.7.0 - 2022-06-28
### Added
- Improved support for iTop 3.0

## 1.6.0 - 2022-06-22
### Added
- PHP methods to calculate RD (Rijksdriehoek) coordinates.

## 1.5.0 - 2021-07-02
### Changed
- Improved CSV export compatibility.

## 1.4.0 - 2020-10-06
### Added
- Portuguese translation, thanks to [@rokam](https://www.transifex.com/user/profile/rokam/).

## 1.3.0 - 2020-06-08
### Added
- AGPL license file.

### Changed
- Diaeresis in Dutch translation.
- Code cleanup.
- Import of objects with Geolocation field is now possible.

### Removed
- Support for iTop 2.3 and 2.4 in order to use php 5.6.

## 1.2.0 - 2019-11-21
First public release.

### Added
- Geolocation info added to sample data locations.
