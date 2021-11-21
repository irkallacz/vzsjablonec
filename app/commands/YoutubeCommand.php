<?php

namespace App\Console;

use App\Model\YoutubeService;
use DateTimeZone;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

final class YoutubeCommand extends Command {

	/** @var YoutubeService */
	private $youtubeService;

	/**
	 * YoutubeCommand constructor.
	 * @param YoutubeService $youtubeService
	 */
	public function __construct(YoutubeService $youtubeService)
	{
		parent::__construct();
		$this->youtubeService = $youtubeService;
	}

	protected function configure() {
		$this->setName('cron:youtube')
			->setDescription('Get new videos from our channel');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$output->writeln('Youtube command', Output::VERBOSITY_VERBOSE);

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
					$output->writeln($playlistItem->snippet, Output::VERBOSITY_VERBOSE);
				}
				$nextPageToken = $playlistItems->nextPageToken;
			}
		}

		foreach ($videos as $video) {
			$publishedAt = new \DateTime($video->publishedAt);
			$publishedAt->setTimezone(new DateTimeZone('Europe/Prague'));

			$values = [
				'videoId'     => $video->resourceId->videoId,
				'title'       => $video->title,
				'publishedAt' => $publishedAt
			];

			$output->writeln(join("\t", array_merge(['Video'], $values)), Output::VERBOSITY_VERBOSE);
			$this->youtubeService->addFile($values);
		}
	}
}