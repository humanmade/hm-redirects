# HM Redirects

Allows to redirect one path to another path on the same domain.

## Architecture
Redirects are stored as a custom post type and use the following fields:

- `post_name` to store the md5 hash of the _From_ path. This column is used because it is indexed, and allows fast queries. `md5` is used to simplify the storage.
- `post_title` to store the _From_ path.
- `post_excerpt`to store the the _To_ path.

## Tips
This plugin uses `wp_safe_redirect()` to redirect. You will have to whitelist your redirect target domains using WordPress' `allowed_redirect_hosts` filter, otherwise the redirect will not work.
One way to get a list of redirect target domains is to run the WP-CLI command: `wp hm-redirects find-domains`. Another is to add them dynamically just-in-time using the filter `hm_redirects_matched_redirect`.

## Attributions
Props for the data storage approach to VIP's [WPCOM Legacy Redirector](https://github.com/Automattic/WPCOM-Legacy-Redirector).

## Contributing

### Before tagging a release

* Update the [version string on line 8](hm-redirects.php).

### Running tests
Currently the plugin's automated tests [run against PHP 7.4 and WP 5.8](.github/workflows/phpunit.yml). PHPUnit doesn't need to be installed, however:
```
composer install
docker run --rm -e WP_VERSION=5.8 -v $PWD:/code humanmade/plugin-tester
```
