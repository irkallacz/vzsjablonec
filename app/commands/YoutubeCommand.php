<?php

namespace App\Console;

use App\Model\YoutubeService;
use DateTimeZone;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

final class YoutubeCommand extends Command {

	/** @var \Google_Service_YouTube */
	private $youTubeClient;

	/** @var YoutubeService */
	private $youTubeService;

	/**
	 * YoutubeCommand constructor.
	 * @param \Google_Service_YouTube $youTubeClient
	 * @param YoutubeService $youtubeService
	 */
	public function __construct(\Google_Service_YouTube $youTubeClient, YoutubeService $youtubeService)
	{
		parent::__construct();
		$this->youTubeClient = $youTubeClient;
		$this->youTubeService = $youtubeService;
	}

	protected function configure() {
		$this->setName('cron:youtube')
			->setDescription('Get new videos from our channel');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$output->writeln('Youtube command', Output::VERBOSITY_VERBOSE);


		$videos = [];
		$playlists = $this->youTubeClient->channels->listChannels('contentDetails', ['id' => $this->youTubeService->channelId]);

		foreach ($playlists as $playlist) {
			$playlistId = $playlist->contentDetails->relatedPlaylists->uploads;
			$nextPageToken = '';

			while (!is_null($nextPageToken)) {
				$playlistItems = $this->youTubeClient->playlistItems->listPlaylistItems('snippet', [
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
			$this->youTubeService->addFile($values);
		}
	}
}