# Anime Metadata Cache

This is the server component for the cached anidb.net kodi metadata scraper. 
This is a proxy service to the anidb.net database, providing metadata about anime 
TV-shows and mapping information from the anidb.net database to the tvdb.com database.

The source code of this projects is provided as is. Do not operate this application 
without knowledge about hosting web applications. Also, there is no access protection: 
you need to restrict the access to a trusted network by using firewalls and/or 
webserver configuration. Be prepared to dig into the php code when the 
applications crashes because of bugs, wrong configuration or network problems.

The mapping between the anidb database and tvdb database is expected to be added
manually using the included basic web interface. Kodi will use a cleaned up version
of the file name of the video files it scans to search in the database. This 
search requires an exact match in the list of available titles. Rename your 
files using exactly one of the anidb titles. For edge cases there is a table 
in the mysql database to provide additional titles which takes precedence over
the anidb titles.

This project was started for my own need to replace the kodi anidb metadata scrapers
provided by [scudlee](https://forum.kodi.tv/showthread.php?tid=142835) and 
[bambi](https://kodi.wiki/view/Add-on:AniDB.net). For me, this replaces hacking
together regular expressions and modifying big XML files with object oriented
PHP development and RDMS maintenance, which I am familiar with.

The metatdata is fetched from theses services (all rights reserved to the respective communities):

- [anidb.net]( https://anidb.net/ )
- [TheTVDB]( https://thetvdb.com/ )

# Installation

The software is installed like most php web applications. Care is taken, to keep
the external dependencies low and available from the current Ubuntu LTS repositories.

```sh
sudo apt-get install --no-install-recommends \
        apache2 ca-certificates libapache2-mod-php7.0 \
        php7.0-cli php7.0-curl php7.0-json php7.0-mysql php7.0-xml php-redis
sudo apt-get install --no-install-recommends redis-server
```

Clone the repository or download an archive. Place the sub-directory `src` 
into your web server's document root. Copy the file `config.dist.php` to `config.php`
and adjust the settings. 

Load the file `database-schema.sql` into the MySQL database.
Note that it is very improbable that there will be automatic schema updates provided:
When updating the app, make sure to update the schema and migrate the data. 

You may want to tune redis to save snapshots often, as loosing valid cache data
too often may get your IP address banned on the anidb API for an unknown amount of time.
You may also want set a longer execution time (`max_execution_time`) in `php.ini`,
since it takes some time to index all anime titles after a cache miss.

# Developing

Documentation of the APIs used:

- https://api.thetvdb.com/swagger#/
- https://wiki.anidb.net/w/HTTP_API_Definition
- https://wiki.anidb.net/w/API#Anime_Titles
- https://github.com/phpredis/phpredis

The API calls are cached to a Redis database. For more persistent data, a MySQL 
database is used.

You may want to modify some of the advanced configuration values in `config-app.php`
to redirect the API calls to anidb.net to a mock version on your local network 
or to override the API token for tvdb.com.

All data classes have public members as implicit design by contract. For other 
classes use setters and getters.
