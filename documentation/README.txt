Requirements for DNS Extended :

apache on a Linux/Unix host [ Windows based Apache installs have not been tested ]

php 5.x
pear MDB2 package : http://pear.php.net/package/MDB2

whichever MDB2 driver is required for your chosen DB backend
          [ this project currently supports MySQL, PostgreSQL and MSSQL ]

optional Auth::LDAP pear package if you wish to use authentication and domain separation
         [ auth assigns ownership to newly created domains - if auth is not implemented all zones are visible by default ]

Review config.php.example for example options before configuring config.php although the config options should be rather self explanatory :-)
There are sample skeleton schema files included for MySQL, Postgres and MSSQL, review them and modify as necessary to fit your installation
as the table names and database don't require any static naming scheme. 