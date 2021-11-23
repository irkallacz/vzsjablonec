<?php

namespace App\Console;

use App\Model\YoutubeService;
use DateTimeZone;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Google_Service_YouTube;

final class YoutubeCommand extends Command {

	/** @var Google_Service_YouTube */
	private $youTubeClient;

	/** @var YoutubeService */
	private $youTubeService;

	/** @var string */
	private $channelId;

	/**
	 * YoutubeCommand constructor.
	 * @param string $channelId
	 * @param \Google_Service_YouTube $youTubeClient
	 * @param YoutubeService $youtubeService
	 */
	public function __construct(string $channelId, YoutubeService $youtubeService, Google_Service_YouTube $youTubeClient)
	{
		parent::__construct();

		$this->youTubeClient = $youTubeClient;
		$this->youTubeService = $youtubeService;
		$this->channelId = $channelId;
	}

	protected function configure() {
		$this->setName('cron:youtube')
			->setDescription('Get new videos from our channel');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$output->writeln('<info>Youtube command</info>', Output::VERBOSITY_VERBOSE);

		$videos = [];
		$playlists = $this->youTubeClient->channels->listChannels('contentDetails', ['id' => $this->channelId]);

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
				}
				$nextPageToken = $playlistItems->nextPageToken;
			}
		}

		if (count($videos)) {
			$output->writeln(sprintf('<comment>Found %d videos</comment>', count($videos)), Output::VERBOSITY_VERBOSE);

			$this->youTubeService->beginTransaction();

			$output->writeln('Truncate table', Output::VERBOSITY_VERBOSE);
			$this->youTubeService->emptyTable();

			foreach ($videos as $video) {
				$values = [
					'videoId'     => $video->resourceId->videoId,
					'title'       => $video->title,
					'publishedAt' => $video->publishedAt
				];

				$output->writeln(join("\t", $values), Output::VERBOSITY_VERBOSE);

				$values['publishedAt'] = new \DateTime($values['publishedAt']);
				$values['publishedAt']->setTimezone(new DateTimeZone('Europe/Prague'));

				$this->youTubeService->addFile($values);
			}

			$this->youTubeService->commitTransaction();
		} else {
			$output->writeln('<comment>No videos found</comment>', Output::VERBOSITY_VERBOSE);
		}

	}
}