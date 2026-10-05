# EXT: preview

[![TYPO3 compatibility](https://img.shields.io/badge/TYPO3-13-ff8700?maxAge=3600&logo=typo3)](https://get.typo3.org/)
[![pipeline status](https://hecate.f7.de/public_extensions/ext_preview/badges/main/pipeline.svg)](https://hecate.f7.de/public_extensions/ext_preview/-/commits/main)

preview is an extension for TYPO3. It adds the functionality to preview disabled pages
through generated preview URLs without the need for a backend login.

## Requirements

* TYPO3 13.4 or 14.3

## Installation and Setup
Install the extension via your preferred way. The extension will add one database table to the database.
No further setup is required. The extension works out of the box.

## What is does
The extensions shows a container above the page content in the page module on every page that is currently
hidden for any reason (time constraint or simply deactivated). For every page (in every language) a unique access
URL can be generated and provided to external partners, which may then access the hidden page without a backend
login.

Every preview URL has its own lifetime (configurable in extension settings). If this lifetime is over, the preview
URL will be automatically removed from the system and is no longer valid.

For any other website visitor the disabled pages are still not accessible.

## How it works
For every page and its language a secret token is stored in the database while a preview link is generated. While accessing
the unpublished page, a PSR-15 middleware determines, if the secret token is provided and grants access, if so. If no
access token is provided, the middleware do nothing and let TYPO3 proceed (which leads to a 404 http error, as the requested
page is not available).

---
