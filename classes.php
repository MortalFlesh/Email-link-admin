<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'Render.php';

class Validator
{
    /**
     * @param array $validIps
     * @return bool
     */
    public function validIps(array $validIps)
    {
        $currentIp = $this->getClientIp();

        return in_array($currentIp, $validIps, true);
    }

    /**
     * @return string
     */
    private function getClientIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }
}

class Http
{
    public function forbidden()
    {
        header('HTTP/1.0 403 Forbidden');
        die('Forbidden');
    }

    /**
     * @param string $url
     */
    public function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * @return string
     */
    public function getCurrentLocation()
    {
        $url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $parts = explode('/', $url);
        array_pop($parts);

        return 'http://' . implode('/', $parts) . '/';
    }

    /**
     * @param string $mimeType
     * @return self
     */
    public function image($mimeType)
    {
        header(sprintf('Content-type: %s', $mimeType));

        return $this;
    }

    /**
     * @return self
     */
    public function noCache()
    {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        return $this;
    }

    /**
     * @param array $data
     */
    public function jsonResponse(array $data)
    {
        $json = json_encode($data);

        echo $json;
        exit;
    }
}

class Admin
{
    /** @var Database */
    private $database;

    /** @var Uploader */
    private $uploader;

    /** @var Render */
    private $render;

    /** @var Http */
    private $http;

    /** @var FlashMessages */
    private $flashMessages;

    /** @var int */
    private $selectedProfile = 1;

    /**
     * @param Database $database
     * @param Uploader $uploader
     * @param Render $render
     * @param Http $http
     * @param FlashMessages $flashMessages
     */
    public function __construct(
        Database $database,
        Uploader $uploader,
        Render $render,
        Http $http,
        FlashMessages $flashMessages
    ) {
        $this->database = $database;
        $this->uploader = $uploader;
        $this->render = $render;
        $this->http = $http;
        $this->flashMessages = $flashMessages;
    }

    /**
     * @return self
     */
    public function handleRequest()
    {
        $post = $_POST;
        $get = $_GET;

        if (!empty($get)) {
            $this->handleGet($get);
        }

        if (!empty($post)) {
            $this->handlePost($post);
        }

        return $this;
    }

    /**
     * @param array $get
     */
    private function handleGet(array $get)
    {
        if (isset($get['profile'])) {
            $this->selectedProfile = (int) $get['profile'];
        }
    }

    /**
     * @param array $post
     */
    private function handlePost(array $post)
    {
        if ($post['save']) {
            $isUploaded = false;

            $emailLink = new EmailLinkEntity();
            $emailLink->setUrl($post['url']);
            $emailLink->setProfileId($this->selectedProfile);

            if ($this->uploader->isFileUploading()) {
                $imageName = $this->uploader
                    ->uploadImage($this->selectedProfile)
                    ->getImageName($this->selectedProfile);

                $emailLink->setImage($imageName);

                $isUploaded = true;
            }

            try {
                $this->database->saveAdminLink($emailLink, $isUploaded);

                $this->flashMessages->addSuccess('Uloženo v pořádku.');
            } catch (\Exception $e) {
                $this->flashMessages->addError($e->getMessage());
            }

            $this->http->redirect($_SERVER['HTTP_REFERER']);
        } elseif (array_key_exists('action', $post) && $post['action'] === 'add-profile') {
            $newProfileName = $post['profile'];

            if (!empty($newProfileName)) {
                $profile = $this->database->addNewProfile($newProfileName);
                $this->flashMessages->addSuccess('Profil byl vytvořen.');

                $this->http->jsonResponse(['status' => 'ok', 'id' => $profile->getId()]);
            } else {
                $this->http->jsonResponse(['status' => 'error']);
            }
        } elseif (array_key_exists('action', $post) && $post['action'] === 'rename-profile') {
            $profileId = (int) $post['profile'];
            $newProfileName = $post['name'];

            if ($profileId && !empty($newProfileName)) {
                $this->database->renameProfile($profileId, $newProfileName);
                $this->flashMessages->addSuccess('Profil byl přejmenován.');

                $this->http->jsonResponse(['status' => 'ok', 'id' => $profileId]);
            } else {
                $this->http->jsonResponse(['status' => 'error']);
            }
        } elseif (array_key_exists('action', $post) && $post['action'] === 'remove-profile') {
            $profileId = (int) $post['profile'];

            if ($profileId) {
                $profile = $this->database->loadEmailLink($profileId);

                $this->database->removeProfile($profileId);
                $this->uploader->removeImage($profile->getImage());

                $this->flashMessages->addSuccess('Profil byl odstraněn.');

                $this->http->jsonResponse(['status' => 'ok', 'id' => 1]);
            } else {
                $this->http->jsonResponse(['status' => 'error']);
            }
        }
    }

    public function renderPage()
    {
        $profiles = $this->database->loadProfiles();
        $emailLink = $this->database->loadEmailLink($this->selectedProfile);

        $host = $this->http->getCurrentLocation();

        $this->render
            ->renderHeader()
            ->renderTitle()
            ->renderFlashMessages($this->flashMessages->getMessages())
            ->renderProfileSelector($profiles, $this->selectedProfile)
            ->renderForm($emailLink)
            ->renderSeparator()
            ->renderUsage(
                $host . 'link.php?p=' . $this->selectedProfile,
                $host . 'image.php?p=' . $this->selectedProfile
            )
            ->renderPreview($emailLink)
            ->renderFooter();
    }
}

class Database
{
    /** @var PDO */
    private $pdo;

    /**
     * @param array $dbConfig
     */
    public function __construct(array $dbConfig)
    {
        $this->pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8', $dbConfig['host'], $dbConfig['dbname']),
            $dbConfig['username'],
            $dbConfig['password']
        );
        $this->pdo->exec("set names utf8");
    }

    /**
     * @return ProfileEntity[]
     */
    public function loadProfiles()
    {
        $profiles = [];

        $stmt = $this->pdo->prepare('SELECT id, title FROM profile ORDER BY title');
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as $item) {
            $profiles[] = new ProfileEntity($item['id'], $item['title']);
        }

        return $profiles;
    }

    /**
     * @param int $selectedProfile
     * @return EmailLinkEntity
     */
    public function loadEmailLink($selectedProfile)
    {
        $emailLink = new EmailLinkEntity();

        $stmt = $this->pdo->prepare('SELECT url, image FROM emaillink WHERE profile_id = :profile');
        $stmt->bindParam(':profile', $selectedProfile);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $emailLink->setUrl($data['url']);
        $emailLink->setImage($data['image']);

        return $emailLink;
    }

    /**
     * @param EmailLinkEntity $emailLink
     * @param bool $isUploaded
     */
    public function saveAdminLink(EmailLinkEntity $emailLink, $isUploaded)
    {
        $url = $emailLink->getUrl();
        $image = $emailLink->getImage();
        $profile = $emailLink->getProfileId();

        $stmt = $isUploaded ? $this->getSaveStmt($url, $image) : $this->getSaveStmtForUrl($url);
        $stmt->bindParam(':profile', $profile);

        $stmt->execute();

        if ($stmt->errorCode() > 0) {
            throw new \Exception('Při uložení se vyskytla chyba.');
        }
    }

    /**
     * @param string $url
     * @param string $image
     * @return PDOStatement
     */
    private function getSaveStmt($url, $image)
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO emaillink (profile_id, url, image)
            VALUES (:profile, :url, :image)
            ON DUPLICATE KEY UPDATE
            url = VALUES(url),
            image = VALUES(image)
        ');

        $stmt->bindParam(':url', $url);
        $stmt->bindParam(':image', $image);

        return $stmt;
    }

    /**
     * @param string $url
     * @return PDOStatement
     */
    private function getSaveStmtForUrl($url)
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO emaillink (profile_id, url, image)
            VALUES (:profile, :url, "")
            ON DUPLICATE KEY UPDATE
            url = VALUES(url)
        ');

        $stmt->bindParam(':url', $url);

        return $stmt;
    }

    /**
     * @param string $newProfileName
     * @return ProfileEntity
     */
    public function addNewProfile($newProfileName)
    {
        $stmt = $this->pdo->prepare('INSERT INTO profile (title) VALUES (:title)');
        $stmt->bindParam(':title', $newProfileName);
        $stmt->execute();

        $newProfileId = $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare('SELECT id, title FROM profile WHERE id = :id');
        $stmt->bindParam(':id', $newProfileId);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return new ProfileEntity($data['id'], $data['title']);
    }

    /**
     * @param int $profileId
     * @param string $newProfileName
     */
    public function renameProfile($profileId, $newProfileName)
    {
        $stmt = $this->pdo->prepare('UPDATE profile SET title = :title WHERE id = :id');
        $stmt->bindParam(':title', $newProfileName);
        $stmt->bindParam(':id', $profileId);
        $stmt->execute();
    }

    /**
     * @param int $profileId
     */
    public function removeProfile($profileId)
    {
        $stmt = $this->pdo->prepare('DELETE FROM emaillink WHERE profile_id = :id');
        $stmt->bindParam(':id', $profileId);
        $stmt->execute();

        $stmt = $this->pdo->prepare('DELETE FROM profile WHERE id = :id');
        $stmt->bindParam(':id', $profileId);
        $stmt->execute();
    }
}

class EmailLinkEntity
{
    /** @var string */
    private $url = '';

    /** @var string */
    private $image = '';

    /** @var int */
    private $profileId = 1;

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = trim($url);
    }

    /**
     * @return string
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * @param string $image
     */
    public function setImage($image)
    {
        $this->image = $image;
    }

    /**
     * @return int
     */
    public function getProfileId()
    {
        return $this->profileId;
    }

    /**
     * @param int $profileId
     */
    public function setProfileId($profileId)
    {
        $this->profileId = (int) $profileId;
    }
}


class FlashMessages
{
    const SESSION_NAME = 'ses_flash_msgs';

    /** @var FlashMessage[] */
    private $flashMassages = [];

    public function __construct()
    {
        $this->flashMassages = &$_SESSION[self::SESSION_NAME];

        if (!is_array($this->flashMassages)) {
            $this->flashMassages = [];
        }
    }


    /**
     * @param string $msg
     */
    public function addSuccess($msg)
    {
        $this->addFlash(new FlashMessage($msg, FlashMessage::SUCCESS));
    }

    /**
     * @param FlashMessage $flashMessage
     */
    private function addFlash(FlashMessage $flashMessage)
    {
        $this->flashMassages[] = $flashMessage->serialize();
    }

    /**
     * @param string $msg
     */
    public function addError($msg)
    {
        $this->addFlash(new FlashMessage($msg, FlashMessage::ERROR));
    }

    /**
     * @return FlashMessage[]
     */
    public function getMessages()
    {
        $flashMessages = array_map(function (array $data) {
            return FlashMessage::deserialize($data);
        }, $this->flashMassages);

        $this->flashMassages = [];

        return $flashMessages;
    }
}

class FlashMessage
{
    const SUCCESS = 'success';
    const ERROR = 'error';

    /** @var string */
    private $msg;

    /** @var string */
    private $type;

    /**
     * @param string $msg
     * @param string $type
     */
    public function __construct($msg, $type)
    {
        $this->msg = $msg;
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getMsg()
    {
        return $this->msg;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function serialize()
    {
        return [
            'msg' => $this->msg,
            'type' => $this->type,
        ];
    }

    /**
     * @param array $data
     * @return self
     */
    public static function deserialize(array $data)
    {
        return new self($data['msg'], $data['type']);
    }
}

class Uploader
{
    const IMAGE_NAME = 'image';

    /** @var string */
    private $extension;

    /**
     * @return bool
     */
    public function isFileUploading()
    {
        return !empty($_FILES['image']) && is_array($_FILES['image']) && !empty($_FILES['image']['tmp_name']);
    }

    /**
     * @param int $profile
     * @return self
     */
    public function uploadImage($profile)
    {
        $image = $_FILES['image'];

        $this->extension = Image::getExtension($image);
        $path = __DIR__;
        $fullpath = $path . DIRECTORY_SEPARATOR . $this->getImageName($profile);

        move_uploaded_file($image['tmp_name'], $fullpath);

        return $this;
    }

    /**
     * @param int $profile
     * @return string
     */
    public function getImageName($profile)
    {
        return $profile . '_' . self::IMAGE_NAME . '.' . $this->extension;
    }

    /**
     * @param string $imageName
     */
    public function removeImage($imageName)
    {
        $fullpath = __DIR__ . DIRECTORY_SEPARATOR . $imageName;

        if (file_exists($fullpath)) {
            unlink($fullpath);
        }
    }
}

class Link
{
    /** @var Database */
    private $database;

    /** @var Http */
    private $http;

    /**
     * @param Database $database
     * @param Http $http
     */
    public function __construct(Database $database, Http $http)
    {
        $this->database = $database;
        $this->http = $http;
    }

    /**
     * @param int $profile
     */
    public function redirectToLink($profile)
    {
        $emailLink = $this->database->loadEmailLink($profile);

        $this->http->redirect($emailLink->getUrl());
    }
}

class Image
{
    /** @var Database */
    private $database;

    /** @var Http */
    private $http;

    /**
     * @param Database $database
     * @param Http $http
     */
    public function __construct(Database $database, Http $http)
    {
        $this->database = $database;
        $this->http = $http;
    }

    /**
     * @param array|string $image
     * @return string
     */
    public static function getExtension($image)
    {
        $imageName = is_array($image) ? $image['name'] : $image;

        $ext = explode('.', $imageName);

        return strtolower(array_pop($ext));
    }

    /**
     * @param int $profile
     */
    public function renderImage($profile)
    {
        $emailLink = $this->database->loadEmailLink($profile);
        $imageName = $emailLink->getImage();
        $mimeType = $this->getMimeType($imageName);

        $this->http
            ->image($mimeType)
            ->noCache();

        echo file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $imageName);
    }

    /**
     * @param string $imageName
     * @return string
     */
    private function getMimeType($imageName)
    {
        $extension = self::getExtension($imageName);

        switch ($extension) {
            case 'jpg':
                return 'image/jpeg';
            default:
                return sprintf('image/%s', $extension);
        }
    }
}

class ProfileEntity
{
    /** @var int */
    private $id;

    /** @var string */
    private $title;

    /**
     * @param int $id
     * @param string $title
     */
    public function __construct($id, $title)
    {
        $this->id = (int) $id;
        $this->title = (string) $title;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }
}
