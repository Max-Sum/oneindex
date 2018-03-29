#!/bin/sh

set -e

echo "<?php return array (
  'client_id' => '${ONEINDEX_CLIENT_ID}',
  'client_secret' => '${ONEINDEX_CLIENT_SECRET}',
  'redirect_uri' => '${ONEINDEX_REDIRECT_URI}',
  'onedrive_root'=> '${ONEINDEX_DRIVE_ROOT}',
  'cache_type'=> '${ONEINDEX_CACHE_TYPE}',
  'cache_expire_time' => ${ONEINDEX_CACHE_EXPIRE_TIME},
  'cache_refresh_time' => ${ONEINDEX_CACHE_REFRESH_TIME},
  'root_path' => '${ONEINDEX_ROOT_PATH}'
);" > config/base.php

exec "$@"