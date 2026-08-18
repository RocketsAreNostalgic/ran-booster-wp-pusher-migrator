# WordPress.org suitability

**Current decision: do not submit this extension to the WordPress.org Plugin
Directory.** Distribute it only as the verified ZIP attached to its canonical
repository release.

The bridge depends on RAN Booster and declares `Requires Plugins: ran-booster`.
RAN Booster is not currently distributed through the WordPress.org Plugin
Directory. WordPress's Plugin Dependencies guidance states that a dependent
plugin hosted on WordPress.org may declare only dependencies that are also
hosted there. Removing the header would make the directory package less
truthful and weaken WordPress's activation safeguards, so it is not an
acceptable workaround.

The current repository also owns release packaging, checksums, immutable
release readback, and the canonical `Update URI`. Introducing a WordPress.org
package would create a second distribution and update authority that this Beta
has not designed or verified.

Reconsider this decision only if all of the following become true:

- RAN Booster has a compatible WordPress.org package;
- the extension's temporary, dependency-bound purpose is accepted as a useful
  and complete directory plugin;
- one update and release authority is selected and proved end to end;
- a current `readme.txt`, directory assets, support route, and directory release
  process are ready; and
- submission receives separate authorization.

References:

- [Introducing Plugin Dependencies in WordPress 6.5](https://make.wordpress.org/core/2024/03/05/introducing-plugin-dependencies-in-wordpress-6-5/)
- [WordPress.org detailed plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
