<?php

namespace App\Template;

class LatteFilters{

    static $root = '';

	/**
	 * @param $filter
	 * @param $value
	 * @return mixed
	 */
	public static function loader($filter, $value){
		if (method_exists(__CLASS__, $filter)) {
			$args = func_get_args();
			array_shift($args);
			return call_user_func_array([__CLASS__, $filter], $args);
		}
	}

    /**
     * @param $time
     * @return string
     */
    public static function timeAgoInWords($time){
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
    private static function plural($n){
        $args = func_get_args();
        return $args[($n == 1) ? 1 : (($n >= 2 && $n <= 4) ? 2 : 3)];
    }

    /**
     * @param \DateTime $start
     * @param \DateTime $end
     * @return string
     */
    public static function durationInWords(\DateTime $start, \DateTime $end){
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


    /**
     * @param $s
     * @return string
     */
    public static function texy($s){
        $texy = new \Texy\Texy();
        $texy->headingModule->balancing = \Texy\Modules\HeadingModule::FIXED;
        return $texy->process($s);
    }

    /**
     * @param $s
     * @return string
     */
    public static function forumTexy($s){
        $texy = new \Texy\Texy();

        $texy->allowed['emoticon'] = TRUE;
        $texy->emoticonModule->class = 'smile';
        $texy->emoticonModule->root = self::$root . '/texyla/emoticons';
        $texy->emoticonModule->fileRoot = WWW_DIR . '/texyla/emoticons';
        $texy->emoticonModule->icons = [
            ':D'        => '01.gif',
            ':-D'       => '01.gif',
            ':p'        => '02.gif',
            '8)'        => '03.gif',
            '8-)'       => '03.gif',
            ';)'        => '04.gif',
            ';-)'       => '04.gif',
            ':)'        => '05.gif',
            ':-)'       => '05.gif',
            ':?'        => '06.gif',
            ':-?'       => '06.gif',
            ':|'        => '07.gif',
            ':-|'       => '07.gif',
            ':roll:'    => '08.gif',
            ':cry:'     => '09.gif',
            ':bored:'   => '10.gif',
            ':dead:'    => '11.gif',
            ':shock:'   => '12.gif',
            ':evil:'    => '13.gif',
            ':sick:'    => '14.gif',
            ':oops:'    => '15.gif',
            ':love:'    => '16.gif',
            ':('        => '17.gif',
            ':twisted:' => '18.gif',
            ':lol:'     => '19.gif',
            ':?:'       => '20.gif',
            ':!:'       => '21.gif',
            '(y)'       => '22.gif'
        ];

        return $texy->process($s);
    }

}