<?php

$validIps = [
    '::1',  // local
    '90.180.86.81',
];

$dbConfig = [
    'host' => '',
    'username' => '',
    'password' => '',
];

//
// ================= DON'T CHANGE CODE BELOW ========================================================
//

session_start();

$http = new Http();

$validator = new Validator();
if (!$validator->validIps($validIps)) {
    $http->forbidden();
}

$admin = new Admin(new Database($dbConfig), new Render(), $http, new FlashMessages());
$admin
    ->handleRequest()
    ->renderPage();

//
// classes
//

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
}

class Admin
{
    /** @var Database */
    private $database;

    /** @var Render */
    private $render;

    /** @var Http */
    private $http;

    /** @var FlashMessages */
    private $flashMessages;

    /**
     * @param Database $database
     * @param Render $render
     * @param Http $http
     * @param FlashMessages $flashMessages
     */
    public function __construct(Database $database, Render $render, Http $http, FlashMessages $flashMessages)
    {
        $this->database = $database;
        $this->render = $render;
        $this->http = $http;
        $this->flashMessages = $flashMessages;
    }

    public function handleRequest()
    {
        $data = $_POST;

        if (!empty($data) && $data['save']) {
            $emailLink = new EmailLinkEntity();
            $emailLink->setUrl($data['url']);

            try {
                $this->database->saveAdminLink($emailLink);

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
        $this->render
            ->renderTitle()
            ->renderFlashMessages($this->flashMessages->getMessages())
            ->renderForm($this->database->loadEmailLink());
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
     */
    public function saveAdminLink(EmailLinkEntity $emailLink)
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO emaillink (id, url, image)
            VALUES (1, :url, :image)
            ON DUPLICATE KEY UPDATE
                url = VALUES(url),
                image = VALUES(image)
        ');

        $stmt->bindParam(':url', $emailLink->getUrl());
        $stmt->bindParam(':image', $emailLink->getImage());

        $stmt->execute();

        if (!empty($stmt->errorInfo())) {
            throw new \Exception('Při uložení se vysytla chyba.');
        }
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
        foreach($flashMessages as $message) {
            ?>
            <div style="padding: 10px; color: <?php echo $message->getType() === FlashMessage::SUCCESS ? 'green' : 'red' ?>;">
                <strong><?php echo $message->getMsg()?></strong>
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
        $this->flashMassages[] = new FlashMessage($msg, FlashMessage::SUCCESS);
    }

    /**
     * @param string $msg
     */
    public function addError($msg)
    {
        $this->flashMassages[] = new FlashMessage($msg, FlashMessage::ERROR);
    }

    /**
     * @return FlashMessage[]
     */
    public function getMessages()
    {
        $flashMessages = $this->flashMassages;

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
}
