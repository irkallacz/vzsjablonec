<?php

/**
 * Created by PhpStorm.
 * User: Jakub
 * Date: 8.12.2016
 * Time: 21:59
 */
class TexyFactory extends Nette\Object{

    static $root = '';

    /**
     * @return Texy
     */
    public static function createTexy(){
        $texy = new Texy();
        $texy->headingModule->balancing = TexyHeadingModule::FIXED;
        return $texy;
    }

    /**
     * @return Texy
     */
    public static function createForumTexy(){
        $texy = self::createTexy();

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
        
        return $texy;
    }

}