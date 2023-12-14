# Changelog

All notable changes to `antwerpes/typo3-docchecklogin` will be documented in this file.

## 2.0.6 - 2023-12-14

### What's Changed

* Add  new supported doccheck login style parameters. For more information see [doccheck Login changelog](https://docs.doccheck.com/login-implementation/changelog/examples.html#_3-supported-style-parameters)

**Full Changelog**: https://github.com/antwerpes/typo3_docchecklogin/compare/2.0.5...2.0.6

## Release 2.0.5 - 2023-07-06

### What's Changed

- update for compatibility with TYPO3 v12.
- 
- Configuration errors will now be written to a separate log file.
- 
- Rename of the protected Function fetchDummyUserGroup to fetchUserGroup for a more general usage
- 
- Include loggedIn Function in showAction because otherwise the redirect will not work
- 
- Fixed: uniqueKeyGroup had no usage. Before uniqueKeyGroup got overwritten by the DummyUser Group. Now you can define here a custom group id, that will only get overwriten by the routing Feature.
- 

**Full Changelog**: https://github.com/antwerpes/typo3_docchecklogin/compare/1.0.1...2.0.4
