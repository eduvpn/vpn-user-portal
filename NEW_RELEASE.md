# New Release

Before tagging a new release:

1. Update `CHANGES.md`;
2. Update `CONFIG_CHANGES.md` if needed;
3. Run `composer update` to make sure we have the latest version of the 
   dependencies;
4. Run `vendor/bin/put` to run the unit tests;
5. Write the new version number to the `VERSION` file in the project root
6. Commit everything: `git commit -a -m 'prepare for release'`;
7. Push changes: `git push origin v3`, `git push github v3`;
7. Tag release: `git tag 3.1.1 -a -m '3.1.1'` (obviously use the correct 
   version number;
8. Push the tags: `git push origin 3.1.1`, `git push github 3.1.1`
9. Run `make_release`
10. Run `sr.ht_upload_release`
