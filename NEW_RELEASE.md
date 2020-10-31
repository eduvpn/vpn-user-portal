# New Release

Before tagging a new release:

1. Update `CHANGES.md`;
2. Update `CONFIG_CHANGES.md` if needed;
3. Run `composer update` to make sure we have the latest version of the 
   dependencies;
4. Run `vendor/bin/phpunit`;
5. Write the new version number to the `VERSION` file in the project root
6. Commit everything: `git commit -a -m 'prepare for release'`;
7. Push changes: `git push origin v2`, `git push github v2`;
7. Tag release: `git tag 1.2.3 -a -m '1.2.3'` (obviously use the correct 
   version number;
8. Push the tags: `git push origin 1.2.3`, `git push github 1.2.3`
9. Run `make_release`
10. Run `upload_release`
