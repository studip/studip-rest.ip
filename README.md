# REST.ip

## Description

This plugin is supposed to be a working example of a RESTful webservice
implementing authentication by OAuth.

## Integration in Stud.IP

### New API

The API is located at ***installation_url*/plugins.php/restipplugin/api** for now.

Full API documentation will follow.

### Administration of consumers for root administrators

Root administrators can manage which external apps are allowed to access the api on a new page located in **/admin/config**.

Applications can be created, updated and deleted there. It is also possible to activate/deactivate a certain app. Access keys are managed here too.

### Administration of applications for users

Users can manage which external apps are allowed to access their data on a new page located in **/settings/config**.

## Test client

The test client is also implemented in the plugin.

Although the library used for the server side implementation could also manage the client communication, a different library was chosen on purpose to ensure compatibility.

## Credits

- RESTful state is implemented using [Trails](https://github.com/luniki/trails)
- OAuth is implemented using [oauth-php](http://code.google.com/p/oauth-php) on the server side
- OAuth client uses the [OAuth library](https://github.com/zendframework/zf2/tree/master/library/Zend/OAuth) from the [Zend Framework 2.0](http://framework.zend.com/zf2)
