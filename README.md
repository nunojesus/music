# README

[![Build Status](https://secure.travis-ci.org/owncloud/music.png)](http://travis-ci.org/owncloud/music)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/owncloud/music/badges/quality-score.png?s=ddb9090619b6bcf0bf381e87011322dd2514c884)](https://scrutinizer-ci.com/g/owncloud/music/)

## Supported formats

* FLAC (`audio/flac`)
* MP3 (`audio/mpeg`)
* Vorbis in OGG container (`audio/ogg`)
* Opus in OGG container (`audio/ogg` or `audio/opus`)
* WAV (`audio/wav`)
* M4A (`audio/mp4`)
* M4B (`audio/m4b`)

_Note: The audio formats supported vary depending on the browser. Chrome and Firefox should be able to play all the formats listed above. All browsers should be able to play at least the MP3 files._

_Note: It might be unable to play some particular files (on some browsers)._


### Detail

This app utilizes 2 backend players: Aurora.js and SoundManager2.

SoundManager2 utilizes the browser's built-in codecs. Aurora.js, on the other hand, uses Javascript and HTML5 Audio API to decode and play music and doesn't require codecs from browser. The Music app ships with FLAC and MP3 plugins for Aurora.js. Aurora.js does not work on any version of Internet Explorer and fails to play some MP3 files on other browsers, too.

The Music app uses SoundManager2 if the browser has a suitable codec available for the file in question and Aurora.js otherwise. In practice, Aurora.js is always used for FLAC files and also for MP3 files if the browser has no suitable codec available (e.g. Chromium). SoundManager2 is used for everything else, including OGG, MP4, WAV, etc.

## Usage hints

Normally, the Music app detects any new audio files in the filesystem on application start and scans metadata from those to its database tables when the user clicks the prompt. The Music app also detects file removals and modifications on the background and makes the required database changes automatically.

If the database would somehow get corrupted, the user can force it to be rebuilt by navigating to the Personal settings and changing the option "Music" > "Path to your music collection".

### Commands

If preferred, it is also possible to use the command line tool for the database maintenance as described below. This may be quicker than scanning via the web UI in case of large music library, and optionally allows targeting more than one user at once.

Following commands are available(see script occ in your ownCloud root folder):

#### Scan music files

	./occ music:scan USERNAME1 USERNAME2 ...

This scans all not scanned music files of the user USERNAME and saves the extracted metadata into the music tables in the database.

	./occ music:scan --all

This scans music files for all users.

Both of the above commands can be combined with the `--debug` switch, which enables debug output and shows the memory usage of each scan step.

#### Reset scanned metadata

**Warning:** This command will delete data! It will remove unavailable tracks from playlists as playlists are linked against the track metadata.

	./occ music:reset-database USERNAME1 USERNAME2 ...

This will reset the scanned metadata of the provided users.

	./occ music:reset-database --all

This will reset the scanned metadata of all users.

### Ampache

In the settings the URL you need for Ampache is listed and looks like this:

```
https://cloud.domain.org/index.php/apps/music/ampache/
```

This is the common path. Some clients append the last part (`server/xml.server.php`) automatically. If you have connection problems try the longer version of the URL with the `server/xml.server.php` appended.

#### Authentication

To use Ampache you can't use your ownCloud password. Instead, you need to generate APIKEY for Ampache.
Go to "Your username" → "Personal", and check section Music/Ampache, where you can generate your key. Enter your ownCloud username and the generated key as password to your client.

You may use the `/settings/userkey/generate` endpoint to programatically generate a random password. The endpoint expects two parameters, `length` (optional) and `description` (mandatory) and returns a JSON response.
Please note that the minimum password length is 10 characters. The HTTP return codes represent also the status of the request.

```
POST /settings/userkey/generate
```

Parameters:

```
{
	"length": <length>,
	"description": <description>
}
```

Response (success):

```
HTTP/1.1 201 Created

{
	"id": <userkey_id>,
	"password": <random_password>,
	"description": <description>
}
```

Response (error - no description provided):

```
HTTP/1.1 400 Bad request

{
	"message": "Please provide a description"
}
```

Response (error - error while saving password):

```
HTTP/1.1 500 Internal Server Error

{
	"message": "Error while saving the credentials"
}
```

### Installation

Music App can be installed using the App Management in ownCloud. Instructions can be found [here](https://doc.owncloud.org/server/8.1/admin_manual/installation/apps_management_installation.html).

### Known issues

#### Huge music collections

The current version doesn't scale well for huge music collections. There are plans for a kind of paginated version, which hides the pagination and should be useable as known before. #78

#### Application can not be activated because of illegal code

The current music app can't be installed and ownCloud prints following error message:
"Application can not be activated because of illegal code". This is due to the appcodechecker in core (which is kind of broken), but you can do the installation if the appcodechecker is deactivated:

* set `appcodechecker` to `false` in `config.php` (see the [config.sample.php](https://github.com/owncloud/core/blob/a8861c70c8e5876a961f00e49db88843432bf7ba/config/config.sample.php#L164) )
* now you can install the app
* afterwards re-enable the appcodechecker

## Development

### L10n hints

Sometimes translatable strings aren't detected. Try to move the `translate` attribute
more to the beginning of the HTML element.

### Build frontend bundle

All the frontend javascript sources of the Music app, excluding the vendor libraries, are bundled into a single file for deployment. The bundle file is js/public/app.js. Generating it requires make and npm utilities, and happens by running:

	cd build
	make

To automatically regenerate the app.js bundle whenever the source .js files change, use

    make watch

### Build appstore package

	git archive HEAD --format=zip --prefix=music/ > build/music.zip

### Install test dependencies

	composer install

### Run tests

PHP unit tests

	vendor/bin/phpunit --coverage-html coverage-html-unit --configuration tests/php/unit/phpunit.xml tests/php/unit

PHP integration tests

	cd ../..          # owncloud core
	./occ maintenance:install --admin-user admin --admin-pass admin --database sqlite
	./occ app:enable music
	cd apps/music
	vendor/bin/phpunit --coverage-html coverage-html-integration --configuration tests/php/integration/phpunit.xml tests/php/integration

Behat acceptance tests

	cd tests
	cp behat.yml.dist behat.yml
	# add credentials for Ampache API to behat.yml
	../vendor/bin/behat

For the acceptance tests you need to upload all tracks of the following 3 artists:

* https://www.jamendo.com/de/artist/435725/simon-bowman
* https://www.jamendo.com/de/artist/351716/diablo-swing-orchestra
* https://www.jamendo.com/de/artist/3573/pascalb-pascal-boiseau

### 3rdparty libs

update JavaScript libraries

	cd js
	bower update

## API

The music app implements the [Shiva API](https://shiva.readthedocs.org/en/latest/resources/base.html) except the resources `/artists/<int:artist_id>/shows`, `/tracks/<int:track_id>/lyrics` and the meta resources. You can use this API under `https://own.cloud.example.org/index.php/apps/music/api/`.

Beside those mentioned resources following additional resources are implemented:

* `/api/log`
* `/api/collection`
* `/api/file/{fileId}`
* `/api/file/{fileId}/webdav`
* `/api/file/{fileId}/download`
* `/api/scan`
* `/api/scanstate`
* Playlist API at `/api/playlist/`
* Settings API at `/api/settings`
* [Ampache API](https://github.com/ampache/ampache/wiki/XML-API) at `/ampache/server/xml.server.php`

### `/api/log`

Allows to log a message to ownCloud defined log system.

	POST /api/log

Parameters:

	{
		"message": "The message to log"
	}

Response:

	{
		"success": true
	}


### `/api/collection`

Returns all artists with nested albums and each album with nested tracks. The tracks carry file IDs which can be used to obtain WebDAV link for playing with /api/file/{fileId}/webdav.

	GET /api/collection

Response:

	[
		{
			"id": 2,
			"name": "Blind Guardian",
			"albums": [
				{
					"name": "Nightfall in Middle-Earth",
					"year": 1998,
					"disk" : 1,
					"cover": "/index.php/apps/music/api/album/16/cover",
					"id": 16,
					"tracks": [
						{
							"title": "A Dark Passage",
							"number": 21,
							"artistName": "Blind Guardian",
							"artistId": 2,
							"albumId": 16,
							"albumArtistId": 2,
							"files": {
								"audio/mpeg": 1001
							},
							"id": 202
						},
						{
							"title": "Battle of Sudden Flame",
							"number": 12,
							"artistName": "Blind Guardian",
							"artistId": 2,
							"albumId": 16,
							"albumArtistId": 2,
							"files": {
								"audio/mpeg": 1002
							},
							"id": 212
						}
					]
				}
			]
		},
		{
			"id": 3,
			"name": "blink-182",
			"albums": [
				{
					"name": "Stay Together for the Kids",
					"year": 2002,
					"disk" : 1,
					"cover": "/index.php/apps/music/api/album/22/cover",
					"id": 22,
					"tracks": [
						{
							"title": "Stay Together for the Kids",
							"number": 1,
							"artistName": "blink-182",
							"artistId": 3,
							"albumId": 22,
							"albumArtistId": 3,
							"files": {
								"audio/ogg": 1051
							},
							"id": 243
						},
						{
							"title": "The Rock Show (live)",
							"number": 2,
							"artistName": "blink-182",
							"artistId": 3,
							"albumId": 22,
							"albumArtistId": 3,
							"files": {
								"audio/ogg": 1052
							},
							"id": 244
						}
					]
				}
			]
		}
	]
