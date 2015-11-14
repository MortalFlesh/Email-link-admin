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

$validator = new Validator();
if (!$validator->validIps($validIps)) {
    header('HTTP/1.0 403 Forbidden');
    die('Forbidden');
}

$admin = new Admin(new Database($dbConfig), new Render());
$admin->renderPage();

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

class Admin
{
    /** @var Database */
    private $database;

    /** @var Render */
    private $render;

    /**
     * @param Database $database
     * @param Render $render
     */
    public function __construct(Database $database, Render $render)
    {
        $this->database = $database;
        $this->render = $render;
    }

    public function renderPage()
    {
        $emailLink = $this->database->loadEmailLink();

        $this->render
            ->renderTitle()
            ->renderForm($emailLink);
    }
}

class Database
{
    /** @var array */
    private $dbConfig;

    /**
     * @param $dbConfig
     */
    public function __construct($dbConfig)
    {
        $this->dbConfig = $dbConfig;
    }

    /**
     * @return EmailLinkEntity
     */
    public function loadEmailLink()
    {
        // todo

        return new EmailLinkEntity();
    }
}

class EmailLinkEntity
{
    /** @var string */
    private $url = '';

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
        $this->url = $url;
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
