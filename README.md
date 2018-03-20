# HM Redirects

Allows to redirect one path to another path on the same domain.

## Architecture
Redirects are stored as a custom post type and use the following fields:

- `post_name` to store the md5 hash of the _From_ path. This column is used because it is indexed, and allows fast queries. `md5` is used to simplify the storage.
- `post_title` to store the _From_ path.
- `post_excerpt`to store the the _To_ path.

## Attributions
Props for the data storage approach to VIP's [WPCOM Legacy Redirector](https://github.com/Automattic/WPCOM-Legacy-Redirector).
