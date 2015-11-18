<?php

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
     * @return self
     */
    public function image()
    {
        header('Content-type: image/jpeg');

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

    public function handleRequest()
    {
        $data = $_POST;

        if (!empty($data) && $data['save']) {
            $isUploaded = false;

            $emailLink = new EmailLinkEntity();
            $emailLink->setUrl($data['url']);

            if ($this->uploader->isFileUploading()) {
                $imageName = $this->uploader
                    ->uploadImage()
                    ->getImageName();

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
        }

        return $this;
    }

    public function renderPage()
    {
        $emailLink = $this->database->loadEmailLink();

        $host = $this->http->getCurrentLocation();

        $this->render
            ->renderHeader()
            ->renderTitle()
            ->renderFlashMessages($this->flashMessages->getMessages())
            ->renderForm($emailLink)
            ->renderSeparator()
            ->renderUsage($host . 'link.php', $host . 'image.php')
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
            sprintf('mysql:host=%s;dbname=%s', $dbConfig['host'], $dbConfig['dbname']),
            $dbConfig['username'],
            $dbConfig['password']
        );
    }

    /**
     * @return EmailLinkEntity
     */
    public function loadEmailLink()
    {
        $emailLink = new EmailLinkEntity();

        $stmt = $this->pdo->prepare('SELECT url, image FROM emaillink WHERE id = 1');
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

        $stmt = $isUploaded ? $this->getSaveStmt($url, $image) : $this->getSaveStmtForUrl($url);

        $stmt->execute();

        if ($stmt->errorCode() > 0) {
            throw new \Exception('Při uložení se vysytla chyba.');
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
            INSERT INTO emaillink (id, url, image)
            VALUES (1, :url, :image)
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
            INSERT INTO emaillink (id, url)
            VALUES (1, :url)
            ON DUPLICATE KEY UPDATE
            url = VALUES(url)
        ');

        $stmt->bindParam(':url', $url);

        return $stmt;
    }
}

class EmailLinkEntity
{
    /** @var string */
    private $url = '';

    /** @var string */
    private $image = '';

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
}

class Render
{
    /**
     * @return self
     */
    public function renderHeader()
    {
        ?><!DOCTYPE html>
        <html lang="cs">
        <head>
            <title>Emailadmin</title>
            <meta charset="UTF-8">
        </head>
        <body><?php

        return $this;
    }

    /**
     * @return self
     */
    public function renderTitle()
    {
        ?><h1>Soubory do e-mailů</h1><?php

        return $this;
    }

    /**
     * @param FlashMessage[] $flashMessages
     * @return self
     */
    public function renderFlashMessages(array $flashMessages)
    {
        foreach ($flashMessages as $message) {
            ?>
            <div
                style="padding: 10px; color: <?php echo $message->getType() === FlashMessage::SUCCESS ? 'green' : 'red' ?>;">
                <strong><?php echo $message->getMsg() ?></strong>
            </div>
            <?php
        }

        return $this;
    }

    /**
     * @param EmailLinkEntity $emailLink
     * @return self
     */
    public function renderForm(EmailLinkEntity $emailLink)
    {
        ?>
        <form action="" method="post" enctype="multipart/form-data">
            <div style="padding-top: 10px;">
                <label>
                    Nastavit nový:
                    <input type="file" name="image"/>
                </label>
            </div>

            <div style="padding-top: 10px;">
                <label>
                    URL:
                    <input type="text" name="url" value="<?php echo $emailLink->getUrl() ?>" style="width: 95%;"/>
                </label>
            </div>

            <div style="padding-top: 10px;">
                <input type="submit" name="save" value="Potvrdit"/>
            </div>
        </form>
        <?php

        return $this;
    }

    /**
     * @param string $link
     * @param string $imgSrc
     * @return $this
     */
    public function renderUsage($link, $imgSrc)
    {
        ?>
        <div>
            <div style="padding-top: 10px;">
                <label>
                    <strong>Link k vložení do e-mailu:</strong>
                    <input type="text" value="<?php echo $link ?>" readonly style="width: 100%;"/>
                </label>
            </div>

            <div style="padding-top: 10px;">
                <label>
                    <strong>Zdroj obrázku k vložení do e-mailu:</strong>
                    <input type="text" value="<?php echo $imgSrc ?>" readonly style="width: 100%;"/>
                </label>
            </div>

            <div style="padding-top: 10px;">
                <label>
                    <strong>Možné použití v e-mailu:</strong>
                    <br>
                    <textarea readonly style="height: 100px; width: 500px;"><?php
?><a href="<?php echo $link ?>">
    <img src="<?php echo $imgSrc ?>">
</a><?php
                        ?></textarea>
                </label>
            </div>
        </div>
        <?php

        return $this;
    }

    /**
     * @param EmailLinkEntity $emailLink
     * @return self
     */
    public function renderPreview(EmailLinkEntity $emailLink)
    {
        ?>
        <div>
            <h3>Aktuální obrázek</h3>

            <div>
                <img src="./<?php echo $emailLink->getImage() ?>"
            </div>
        </div>
        <?php

        return $this;
    }

    /**
     * @return self
     */
    public function renderSeparator()
    {
        ?>
        <hr style="margin: 20px 0;"><?php

        return $this;
    }

    /**
     * @return self
     */
    public function renderFooter()
    {
        ?></body></html><?php

        return $this;
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
     * @return self
     */
    public function uploadImage()
    {
        $image = $_FILES['image'];

        $this->extension = $this->getExtension($image);
        $path = __DIR__;
        $fullpath = $path . DIRECTORY_SEPARATOR . $this->getImageName();

        move_uploaded_file($image['tmp_name'], $fullpath);

        return $this;
    }

    /**
     * @param array $image
     * @return string
     */
    private function getExtension(array $image)
    {
        $ext = explode('.', $image['name']);

        return strtolower(array_pop($ext));
    }

    /**
     * @return string
     */
    public function getImageName()
    {
        return self::IMAGE_NAME . '.' . $this->extension;
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

    public function redirectToLink()
    {
        $emailLink = $this->database->loadEmailLink();

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

    public function renderImage()
    {
        $emailLink = $this->database->loadEmailLink();

        $this->http
            ->image()
            ->noCache();

        echo file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $emailLink->getImage());
    }
}
