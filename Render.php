<?php

class Render
{
    /**
     * @return self
     */
    public function renderHeader()
    {
        echo '<!DOCTYPE html><html lang="cs">';
        ?><head>
            <title>Emailadmin</title>
            <meta charset="UTF-8">
            <script src="./bower_components/jQuery/jquery.min.js"></script>
            <script src="./jquery.app.js"></script>
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
        ?><hr style="margin: 20px 0;"><?php

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

    /**
     * @param ProfileEntity[] $profiles
     * @param int $selectedProfile
     * @return self
     */
    public function renderProfileSelector(array $profiles, $selectedProfile)
    {
        ?>
        <div>
            <form id="js-profile-form" method="get">
                <h3>Vyberte profil:</h3>
                <select name="profile" class="js-select-profile">
                    <?php
                    foreach ($profiles as $profile) {
                        $selected = ($profile->getId() === $selectedProfile ? 'selected="selected"' : '');

                        ?><option value="<?php echo $profile->getId();?>"<?php echo $selected;?>>
                        <?php echo $profile->getTitle();
                        ?></option><?php
                    }
                    ?>
                </select>

                <button type="button" id="js-profile-add">+ Přidat profil</button>
            </form>
        </div>
        <?php

        return $this->renderSeparator();
    }
}
