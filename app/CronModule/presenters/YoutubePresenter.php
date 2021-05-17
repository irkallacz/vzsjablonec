<?php

namespace App\CronModule\Presenters;

use App\Model\YoutubeService;
use DateTimeZone;

class YoutubePresenter extends BasePresenter {

	/** @var YoutubeService @inject */
	public $youtubeService;

	public function actionDefault() {
		$youtube = new \Google_Service_YouTube($this->youtubeService->googleClient);

		$videos = [];
		$playlists = $youtube->channels->listChannels('contentDetails', ['id' => $this->youtubeService->channelId]);

		foreach ($playlists as $playlist) {
			$playlistId = $playlist->contentDetails->relatedPlaylists->uploads;
			$nextPageToken = '';

			while (!is_null($nextPageToken)) {
				$playlistItems = $youtube->playlistItems->listPlaylistItems('snippet', [
					'playlistId' => $playlistId,
					'maxResults' => 50,
					'pageToken'  => $nextPageToken
				]);
				foreach ($playlistItems as $playlistItem) {
					$videos[] = $playlistItem->snippet;
				}
				$nextPageToken = $playlistItems->nextPageToken;
			}
		}

		$this->template->videos = $videos;

		foreach ($videos as $video) {
			$publishedAt = new \DateTime($video->publishedAt);
			$publishedAt->setTimezone(new DateTimeZone('Europe/Prague'));
			$this->youtubeService->addFile([
					'videoId'     => $video->resourceId->videoId,
					'title'       => $video->title,
					'publishedAt' => $publishedAt
				]);
		}
	}
}