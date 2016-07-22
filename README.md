### Pokémon GO Service Status Checker

With this website you can check the status of all Pokémon Go servers.

#### Dependencies

The status checker requires memcached (php).
[How to install](http://php.net/manual/en/book.memcached.php)
It is easier to do this on a unix OS.

#### Usage

php -f /backend/cron.php

This will create the status.json file in your /backend/ directory.
It would be better to do this via a cronjob.

#### Project info

The original code of this website is made by [xPaw](https://github.com/xPaw) and theaqua.

[Original Sourcecode](https://github.com/xPaw/mcstatus)

All code released as-is, under [MIT](LICENSE) license.

*`bg.jpg` taken from subtlepatterns.com*
