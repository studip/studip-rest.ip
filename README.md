# REST.ip

## Description

This plugin is supposed to be a working example of a RESTful webservice
implementing authentication by OAuth.

## Integration in Stud.IP

### Installation

Clone this repository including it's submodules: `git clone --recursive git@github.com:studip/studip-rest.ip.git`

Create a zip archive of the directory and install it via Stud.IP's plugin manager.

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

## License

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or any later version.
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

