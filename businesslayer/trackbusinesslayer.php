<?php

/**
 * ownCloud - Music app
 *
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Music\BusinessLayer;

use \OCA\Music\AppFramework\BusinessLayer\BusinessLayer;
use \OCA\Music\AppFramework\Core\Logger;

use \OCA\Music\Db\TrackMapper;
use \OCA\Music\Db\Track;

use \OCP\AppFramework\Db\DoesNotExistException;


class TrackBusinessLayer extends BusinessLayer {

	private $logger;

	public function __construct(TrackMapper $trackMapper, Logger $logger){
		parent::__construct($trackMapper);
		$this->logger = $logger;
	}

	/**
	 * Returns all tracks filtered by artist
	 * @param string $artistId the id of the artist
	 * @param string $userId the name of the user
	 * @return array of tracks
	 */
	public function findAllByArtist($artistId, $userId){
		return $this->mapper->findAllByArtist($artistId, $userId);
	}

	/**
	 * Returns all tracks filtered by album
	 * @param string $albumId the id of the track
	 * @param string $userId the name of the user
	 * @return \OCA\Music\Db\Track[] tracks
	 */
	public function findAllByAlbum($albumId, $userId, $artistId = null){
		return $this->mapper->findAllByAlbum($albumId, $userId, $artistId);
	}

	/**
	 * Returns the track for a file id
	 * @param string $fileId the file id of the track
	 * @param string $userId the name of the user
	 * @return \OCA\Music\Db\Track track
	 */
	public function findByFileId($fileId, $userId){
		return $this->mapper->findByFileId($fileId, $userId);
	}

	/**
	 * Returns file IDs of all indexed tracks of the user
	 * @param string $userId
	 * @return int[]
	 */
	public function findAllFileIds($userId){
		return $this->mapper->findAllFileIds($userId);
	}

	/**
	 * Adds a track if it does not exist already or updates an existing track
	 * @param string $title the title of the track
	 * @param string $number the number of the track
	 * @param string $artistId the artist id of the track
	 * @param string $albumId the album id of the track
	 * @param string $fileId the file id of the track
	 * @param string $mimetype the mimetype of the track
	 * @param string $userId the name of the user
	 * @param int $length track length in seconds
	 * @param int $bitrate track bitrate in bits (not kbits)
	 * @return \OCA\Music\Db\Track The added/updated track
	 */
	public function addOrUpdateTrack(
			$title, $number, $artistId, $albumId, $fileId,
			$mimetype, $userId, $length=null, $bitrate=null){
		$track = new Track();
		$track->setTitle($title);
		$track->setNumber($number);
		$track->setArtistId($artistId);
		$track->setAlbumId($albumId);
		$track->setFileId($fileId);
		$track->setMimetype($mimetype);
		$track->setUserId($userId);
		$track->setLength($length);
		$track->setBitrate($bitrate);
		return $this->mapper->insertOrUpdate($track);
	}

	/**
	 * Deletes a track
	 * @param int $fileId the file id of the track
	 * @param string|null $userId the name of the user; if omitted, the tracks matching the
	 *                            $fileId are deleted from all users
	 * @return False if no such track was found; otherwise array of six arrays
	 *         (named 'deletedTracks', 'remainingAlbums', 'remainingArtists', 'obsoleteAlbums', 
	 *         'obsoleteArtists', and 'affectedUsers'). These contain the track, album, artist, and
	 *         user IDs of the deleted tracks. The 'obsolete' entities are such which no longer
	 *         have any tracks while 'remaining' entities have some left.
	 */
	public function deleteTrack($fileId, $userId = null){
		if ($userId !== null) {
			try {
				$tracks = [$this->mapper->findByFileId($fileId, $userId)];
			} catch (DoesNotExistException $ex) {
				$tracks = [];
			}
		}
		else {
			$tracks = $this->mapper->findAllByFileId($fileId);
		}

		if(count($tracks) === 0){
			$result = false;
		}
		else{
			$deletedTracks = [];
			$remainingAlbums = [];
			$remainingArtists = [];
			$obsoleteAlbums = [];
			$obsoleteArtists = [];
			$affectedUsers = [];

			foreach($tracks as $track){
				$deletedTracks[] = $track->getId();
				$artistId = $track->getArtistId();
				$albumId = $track->getAlbumId();
				$userId = $track->getUserId();

				$this->mapper->delete($track);

				// check if artist became obsolete
				$result = $this->mapper->countByArtist($artistId, $userId);
				if($result === '0'){
					self::addUnique($obsoleteArtists, $artistId);
				}else{
					self::addUnique($remainingArtists, $artistId);
				}

				// check if album became obsolete
				$result = $this->mapper->countByAlbum($albumId, $userId);
				if($result === '0'){
					self::addUnique($obsoleteAlbums, $albumId);
				}else{
					self::addUnique($remainingAlbums, $albumId);
				}

				self::addUnique($affectedUsers, $userId);
			}

			$result = [
				'deletedTracks'    => $deletedTracks,
				'remainingAlbums'  => $remainingAlbums,
				'remainingArtists' => $remainingArtists,
				'obsoleteAlbums'   => $obsoleteAlbums,
				'obsoleteArtists'  => $obsoleteArtists,
				'affectedUsers'    => $affectedUsers
			];
		}

		return $result;
	}

	/**
	 * Returns all tracks filtered by name (of track/album/artist)
	 * @param string $name the name of the track/album/artist
	 * @param string $userId the name of the user
	 * @return \OCA\Music\Db\Track[] tracks
	 */
	public function findAllByNameRecursive($name, $userId){
		return $this->mapper->findAllByNameRecursive($name, $userId);
	}

	/**
	 * Append value to array if is not already there
	 * @param array $array
	 * @param any $value
	 */
	private static function addUnique(&$array, $value){
		if(!in_array($value, $array)){
			$array[] = $value;
		}
	}
}
