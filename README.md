# Email-link-admin

## Before start

### config.php
- set valid ip addresses for access the admin
- set db credentials

### db initialization

```
CREATE TABLE IF NOT EXISTS `emaillink` (
  `id` int(11) NOT NULL,
  `url` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `image` varchar(255) COLLATE utf8_czech_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
```
