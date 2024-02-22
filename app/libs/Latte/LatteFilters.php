<?php

namespace App\Template;

use Latte\Runtime\Html;
use Tracy\Debugger;

class LatteFilters {

	/**
	 * @param $filter
	 * @param $value
	 */
	public function loader(string $filter) {
		if (in_array($filter, get_class_methods($this))) {
			return [$this, $filter];
		}

		return null;
	}

	/**
	 * @param \DateTimeInterface $date
	 * @return string
	 */
	public static function datetime(\DateTimeInterface $date) {
		return $date->format('d.m.Y H:i');
	}

	/**
	 * @param \DateTimeInterface $date
	 * @return string
	 */
	public static function datetimeN(\DateTimeInterface $date) {
		return strftime('%A %d.%m.%Y %H:%M', $date->format('U'));
	}

	/**
	 * @param \DateTimeInterface $date
	 * @return string
	 */
	public static function datetimeC(\DateTimeInterface $date) {
		return $date->format('c');
	}

	/**
	 * @param int $number
	 * @return string
	 */
	public static function phone(string $number) {
		return number_format((int) $number,0,'',' ');
	}

	/**
	 * @param int $number
	 * @return Html
	 */
	public static function money(int $number, int $decimals = 0, string $units = 'Kč') {
		return new Html(number_format($number, $decimals,',','&nbsp;') . '&nbsp;' . $units);
	}

	/**
     * @param $time
     * @return string
     */
    public static function timeAgoInWords($time) {
        if (!$time) {
            return FALSE;
        } elseif (is_numeric($time)) {
            $time = (int) $time;
        } elseif ($time instanceof \DateTime) {
            $time = $time->format('U');
        } else {
            $time = strtotime($time);
        }

        $delta = time() - $time;

        if ($delta < 0) {
            $delta = round(abs($delta) / 60);
            if ($delta == 0) return 'za okamžik';
            if ($delta == 1) return 'za minutu';
            if ($delta < 45) return 'za ' . $delta . ' ' . self::plural($delta, 'minuta', 'minuty', 'minut');
            if ($delta < 90) return 'za hodinu';
            if ($delta < 1440) return 'za ' . round($delta / 60) . ' ' . self::plural(round($delta / 60), 'hodina', 'hodiny', 'hodin');
            if ($delta < 2880) return 'zítra';
            if ($delta < 43200) return 'za ' . round($delta / 1440) . ' ' . self::plural(round($delta / 1440), 'den', 'dny', 'dní');
            if ($delta < 86400) return 'za měsíc';
            if ($delta < 525960) return 'za ' . round($delta / 43200) . ' ' . self::plural(round($delta / 43200), 'měsíc', 'měsíce', 'měsíců');
            if ($delta < 1051920) return 'za rok';
            return 'za ' . round($delta / 525960) . ' ' . self::plural(round($delta / 525960), 'rok', 'roky', 'let');
        }

        $delta = round($delta / 60);
        if ($delta == 0) return 'před okamžikem';
        if ($delta == 1) return 'před minutou';
        if ($delta < 45) return "před $delta minutami";
        if ($delta < 90) return 'před hodinou';
        if ($delta < 1440) return 'před ' . round($delta / 60) . ' hodinami';
        if ($delta < 2880) return 'včera';
        if ($delta < 43200) return 'před ' . round($delta / 1440) . ' dny';
        if ($delta < 86400) return 'před měsícem';
        if ($delta < 525960) return 'před ' . round($delta / 43200) . ' měsíci';
        if ($delta < 1051920) return 'před rokem';
        return 'před ' . round($delta / 525960) . ' lety';
    }

    /**
     * Plural: three forms, special cases for 1 and 2, 3, 4.
     * (Slavic family: Slovak, Czech)
     * @param  int
     * @return mixed
     */
    public static function plural($n) {
        $args = func_get_args();
        return $args[($n == 1) ? 1 : (($n >= 2 && $n <= 4) ? 2 : 3)];
    }

	/**
	 * @param \DateTime $start
	 * @param \DateTime $end
	 * @param string $dateFormat
	 * @param string $timeFormat
	 * @return string
	 */
	public static function duration(\DateTime $start, \DateTime $end, string $dateFormat = 'd.m.Y', $timeFormat = 'H:i'){
		$duration = $start->format($dateFormat.' '.$timeFormat.' - ');

    	if ($start->format('Y-m-d') == $end->format('Y-m-d'))
			$duration.= $end->format($timeFormat);
    	else
    		$duration.= $end->format($dateFormat.' '.$timeFormat);

    	return $duration;
    }

    /**
     * @param \DateTime $start
     * @param \DateTime $end
     * @return string
     */
    public static function durationInWords(\DateTime $start, \DateTime $end) {
		$duration = $end->diff($start);

		$string = '';

		if ($duration) {
            if ($duration->y) $string.= $duration->y .' '. self::plural($duration->y, 'rok', 'roky', 'let') . ' ';
			if ($duration->m) $string.= $duration->m .' '. self::plural($duration->m, 'měsíc', 'měsíce', 'měsíců') . ' ';
			if ($duration->d) $string.= $duration->d .' '. self::plural($duration->d, 'den', 'dny', 'dní') . ' ';
			if ($duration->h) $string.= $duration->h .' '. self::plural($duration->h, 'hodina', 'hodiny', 'hodin') . ' ';
			if ($duration->i) $string.= $duration->i .' '. self::plural($duration->i, 'minuta', 'minuty', 'minut') . ' ';
			if ($duration->s) $string.= $duration->s .' '. self::plural($duration->s, 'sekunda', 'sekundy', 'sekund');
        }

        return trim($string);     	
    }

    public static function format(string $text, ...$parameters): string
	{
		return sprintf($text, ...$parameters);
	}


	/**
	 * @return \Texy\Texy
	 */
	private static function createTexy() {
		$texy = new \Texy\Texy();

		$texy->addHandler('phrase', function (\Texy\HandlerInvocation $invocation){
			$el = $invocation->proceed();
			// ověř, že $el je objekt HtmlElement a že jde o element 'a' a uprav jej
			if ($el instanceof \Texy\HtmlElement && $el->getName() === 'a') $el->attrs['target'] = '_blank';

			return $el;
		});

		return $texy;
	}

    /**
     * @param $s
     * @return \Latte\Runtime\Html
     */
    public static function texy($s) {
        $texy = self::createTexy();
        $texy->headingModule->balancing = \Texy\Modules\HeadingModule::FIXED;

		return new \Latte\Runtime\Html($texy->process($s));
    }

	/**
     * @param $s
     * @return \Latte\Runtime\Html
     */
    public static function forumTexy($s) {
        $texy = self::createTexy();

		$texy->allowedTags += ['mark' => TEXY_ALL];

		$emojis = [
            ':D'        => '😀',
            ':-D'       => '😀',
            ':p'        => '😋',
            '8)'        => '😎',
            '8-)'       => '😎',
            ';)'        => '😉',
            ';-)'       => '😉',
            ':)'        => '😃',
            ':-)'       => '😃',
            ':?'        => '😕',
            ':-?'       => '😕',
            ':|'        => '😐',
            ':-|'       => '😐',
            ':/'        => '😕',
            ':\\'       => '😕',
            ':-/'       => '😕',
            ':roll:'    => '🙄',
            ':cry:'     => '😪',
            ':bored:'   => '😴',
            ':dead:'    => '💀',
            ':shock:'   => '😲',
            ':evil:'    => '😠',
            ':sick:'    => '🤢',
            ':oops:'    => '🤨',
            ':love:'    => '❤️',
            ':('        => '☹️',
            ':twisted:' => '😈',
            ':lol:'     => '🤣',
            '(y)'       => '👍',
        ];

		krsort($emojis);
		$pattern = [];
		foreach ($emojis as $char => $emoji) {
			$pattern[] = preg_quote($char, '#') . '+'; // last char can be repeated
		}

		$texy->registerLinePattern(
			function (\Texy\LineParser $parser, array $matches) use ($emojis){
				$match = $matches[0];
				if (array_key_exists($match, $emojis)){
					return $emojis[$match];
				}else {
					return FALSE;
				}
			},
			'#(?<=^|[\x00-\x20])(' . implode('|', $pattern) . ')#',
			'emoji',
			'#' . implode('|', $pattern) . '#'
		);

        return new \Latte\Runtime\Html($texy->process($s));
    }

}