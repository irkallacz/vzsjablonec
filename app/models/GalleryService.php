<?php

/**
 * Model base class.
 */

namespace App\Model;

use Nette;
use Nette\Utils\DateTime;
use Nette\Database\Table\Selection;
use Nette\Database\Table\IRow;

class GalleryService extends Nette\Object{

    /** @var Nette\Database\Context */
    public $database;

    /**
     * GalleryService constructor.
     * @param \Nette\Database\Context $database
     */
    public function __construct(Nette\Database\Context $database){
          $this->database = $database;
    }

    /**
     * @return Selection
     */
    public function getAlbums(){
        return $this->database->table('album');
    }

    /**
     * @param $id
     * @return IRow
     */
    public function getAlbumById($id){
    	return $this->getAlbums()->get($id);
    }

    /**
     * @return Selection
     */
    public function getAlbumsPhotosCount(){
        return $this->getAlbums()->select('album.id, COUNT(:photo.id)AS pocet')->group('album.id');
    }


    /**
     * @param DateTime $datetime
     * @return Selection
     */
    public function getAlbumNews(DateTime $datetime){
        return $this->getAlbums()->order('date_add DESC')->where('date_update > ?',$datetime);
    }

    /**
     * @param DateTime $datetime
     * @return Selection
     */
    public function getPhotoNews(DateTime $datetime){
        return $this->getAlbums()->group('album.id')->where(':photo.date_add > ?',$datetime);
    }

    /**
     * @param $value
     * @return bool|int|IRow
     */
    public function addAlbum($value){
    	return $this->getAlbums()->insert($value);
    }

    /**
     * @return Selection
     */
    public function getPhotos(){
        return $this->database->table('photo');
    }

    /**
     * @param $id
     * @return IRow
     */
    public function getPhotoById($id){
        return $this->getPhotos()->get($id);
    }

    /**
     * @param $value
     * @return bool|int|IRow
     */
    public function addPhoto($value){
        return $this->getPhotos()->insert($value);
    }

    /**
     * @param array $values
     * @return int
     */
    public function deletePhotos(array $values){
        return $this->getPhotos()->where('id',$values)->delete();
    }

    /**
     * @param $id
     * @return Selection
     */
    public function getPhotosByAlbumId($id){
        return $this->getPhotos()->select('*')->where('album_id',$id);
    }

    /**
     * @param $id
     * @return int
     */
    public function getLastLoginByMemberId($id){
        return $this->database->table('member_log')->where('member_id',$id)->max('date_add');
    }

    /**
     * @param $user_id
     * @param DateTime $datetime
     */
    public function addMemberLogin($user_id, DateTime $datetime){
        $this->database->query('INSERT INTO member_log VALUES(?, ?) ON DUPLICATE KEY UPDATE date_add = ?', $user_id, $datetime, $datetime);
    }
}