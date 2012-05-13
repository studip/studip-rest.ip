# REST.ip

## Description

This plugin is supposed to be a working example of a RESTful webservice
implementing authentication by OAuth.

## Integration in Stud.IP

### API location

The API is located at ***installation_url*/plugins.php/restipplugin/api** for now.

Full german API documentation is available [here](http://studip.github.com/studip-rest.ip).

### Administration of consumers for root administrators

Root administrators can manage which external apps are allowed to access the api on a new page located in **/admin/config**.

Applications can be created, updated and deleted there. It is also possible to activate/deactivate a certain app. Access keys are managed here too.

### Administration of applications for users

Users can manage which external apps are allowed to access their data on a new page located in **/settings/config**.

## Credits

- RESTful state is implemented using [Slim](https://github.com/codeguy/Slim)
- OAuth is implemented using [oauth-php](http://code.google.com/p/oauth-php) on the server side
