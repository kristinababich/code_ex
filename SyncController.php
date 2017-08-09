<?php

namespace api\controllers;

use Yii;
use api\controllers\ApiActiveController;
use yii\httpclient\XmlParser;
use yii\httpclient\Response;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use api\models\ApiUser;

class SyncController extends ApiActiveController
{

    private $_accessTokenName = 'access_token';
    private $_errorMessage = 'failure';
    private $_successMessage = 'success';
    private $_fileNameParam = 'filename';
    private $_progressMessage = 'progress';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => HttpBasicAuth::className(),
            'auth'  => [$this, 'auth']
        ];
        return ArrayHelper::merge($behaviors, [
                    'verbs' => [
                        'class'   => VerbFilter::className(),
                        'actions' => [
                            'products' => ['POST'],
                        ],
                    ],
        ]);
    }

    public function auth($username, $password)
    {
        if(empty($username) || empty($password)) {
            echo $this->getErrorMessage() . "\n";
        }

        $user = ApiUser::findOne([
            'username' => $username,
        ]);

        if(empty($user)) {
            echo $this->getErrorMessage() . "\n";
        }

        $isPass = $user->validatePassword($password);

        if(!$isPass) {
            echo $this->getErrorMessage() . "\n";
        }

        $user->generateAccessToken();
        return $this->getSuccessMessage() . "\n" .
                $this->getAccessTokenName() . "\n" .
                $user->access_token . "\n".
                $this->getAccessTokenName() . "=" . $user->access_token . "\n";
    }

    public function actionExchange($mode = 'checkauth', $type = 'catalog')
    {
        Yii::error("actionExchange");
        Yii::getLogger()->flush(true);
        switch ($mode) {
            case 'checkauth' :
                $post = Yii::$app->request->post();
                Yii::info('start checkauth'. \yii\helpers\VarDumper::dumpAsString($post));
                if(empty($post['username']) || empty($post['password'])) {
                    if (isset($_SERVER['PHP_AUTH_USER'])) {
                        $post['username'] = $_SERVER['PHP_AUTH_USER'];
                    }
                    if (isset($_SERVER['PHP_AUTH_PW'])) {
                        $post['password'] = $_SERVER['PHP_AUTH_PW'];
                    }
                }
                if(empty($post['username']) || empty($post['password'])) {
                    echo "failure\n";
                } else {
                    Yii::error('start checkauth'. \yii\helpers\VarDumper::dumpAsString($post));
                    Yii::getLogger()->flush(true);
                    $result = $this->auth($post['username'], $post['password']);
                    echo $result;
                }
                break;
            case 'init' :
                $this->initExchange();
                break;
            case 'file' :
                Yii::error('start file upload');
                Yii::getLogger()->flush(true);
                echo $this->upload();
                break;
            case 'import' :
                Yii::error('start import');
                Yii::getLogger()->flush(true);
                echo $this->import();
                break;
            default :
                break;
        }
    }

    public function upload()
    {   
        $get = Yii::$app->request->get();
        if ($filename = isset($get[$this->getFileNameParam()]) ? $get[$this->getFileNameParam()] : null) {
            Yii::info('file ' .\yii\helpers\VarDumper::dumpAsString($filename) . ' found');
            Yii::info('POST: ' .\yii\helpers\VarDumper::dumpAsString($_POST) . ' found');
            $content = file_get_contents('php://input');
            if (isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
                $content = file_get_contents('php://input');
                $file = file_put_contents(Yii::$app->basePath . '/web/upload/' .$filename, $content);//UploadedFile::getInstanceByName($filename);
                chmod(Yii::$app->basePath . '/web/upload/' .$filename, 0777);
                if (!$file) {
                    Yii::info('file was not upload');
                    Yii::getLogger()->flush(true);
                    return $this->getErrorMessage() . "\n";
                }
                $path = Yii::$app->basePath . '/web/upload/' . '_' . time();
                Yii::info('file was saved');
                Yii::getLogger()->flush(true);
                $arhive = new \ZipArchive();
                $res = $arhive->open(Yii::$app->basePath . '/web/upload/' .$filename);
                if ($arhive->open(Yii::$app->basePath . '/web/upload/' .$filename) == true) {
                    $arhive->extractTo($path);
                    $arhive->close();
                    $this->setParsePath($path);
                    $this->setParseFileName($filename);
                    return $this->getSuccessMessage() . "\n";
                } else {
                    Yii::error('open error');
                    Yii::getLogger()->flush(true);
                }
            }
        } else {
            Yii::info('file was not found');
            Yii::getLogger()->flush(true);
        }
    }

    public function import()
    {
        $import = false;
        $offers = false;
        $files = false;
        $dirs = scandir(Yii::$app->basePath . '/web/upload/' );
        Yii::info('$path ' . \yii\helpers\VarDumper::dumpAsString($dirs));
        foreach ($dirs as $key => $dir) {
            if (!is_dir(Yii::$app->basePath . '/web/upload/' . $dir) || $dir == 'import_files') {
                unset($dirs[$key]);
            }
        }
        $path = Yii::$app->basePath . '/web/upload/' .  end($dirs);
        $get = Yii::$app->request->get();
        $filename = isset($get[$this->getFileNameParam()]) ? $get[$this->getFileNameParam()] : null;
        if (strstr($filename, 'import')) {
            $import = $path . '/' . $filename;
            $offers = str_replace ('import', 'offers', $import);
            if (!file_exists($offers)) {
               $offers = false; 
            }
        }
        if (is_dir ($path . '/import_files')) {
            FileHelper::copyDirectory($path . '/import_files' , \yii\helpers\Url::to('@frontend/web/upload/import_files'));
        }
        if ($import) {
            $this->_parseImport($import);
        }
        if ($offers) {
            $this->_parseOffers($offers);
        }
        if (!$import || !$offers) {
            return $this->getErrorMessage() . "\n";
        }
        Yii::info('import' . $this->getSuccessMessage());
        Yii::getLogger()->flush(true);
        return $this->getSuccessMessage() . "\n";
    }

    private function _parseImport($file)
    {
        $parser = new XmlParser();
        $response = new Response();
        $response->setContent(file_get_contents($file));
        $parseResult = $parser->parse($response);
        $products = $parseResult['Каталог']['Товары']['Товар'];
        if (!isset($products[0])) {
            $products = [(object) $products];
        }

        $count = 0;
        $insert = false;
        $rows = [];
        foreach ($products as $product) {
            $insert = false;
            /* PRODUCT */
            $hash = (array) $product->Ид;
            $code = (array) $product->Артикул;
            $name = (array) $product->Наименование;
            $make = (array) $product->Производитель;
            $image = (array) $product->Картинка;

            $attr = (array) $product->ЗначенияРеквизитов;
            foreach ($attr['ЗначениеРеквизита'] as $item) {
                if ($item->Наименование == 'Вес') {
                    $weight = (array) $item->Значение;
                }
            }
            if (!isset($code[0])) {
                continue;
            }
            if (isset($code[0]) && $product = \common\models\Product::find()->where(['code' => $code[0]])->one()) {
                if (isset($hash[0]) && isset($name[0]) && isset($weight[0]) && isset($make[0])) {
                    if (
                        ($product->hash !=$hash[0] ) ||
                        ($product->name != $name[0]) ||
                        ($product->weight != $weight[0]) ||
                        ($product->make != $make[0])
                    ) {
                        $product->hash = $hash[0];
                        $product->name = $name[0];
                        $product->weight = $weight[0];
                        $product->make = $make[0];
                        $product->save();
                    }
                }
            } else {
                $rows[] = [
                    isset($hash[0]) ? $hash[0] : null,
                    isset($code[0]) ? $code[0] : null, 
                    isset($name[0]) ? $name[0] : null,
                    0,
                    0,
                    0, 
                    isset($weight[0]) ? $weight[0] : null, 
                    isset($image[0]) ? $image[0] : null,
                    isset($make[0]) ? $make[0] : null
                ];
                $count ++;
                if ($count == 1) {
                    $insert = true;
                    Yii::$app->db->createCommand()->batchInsert('{{%product}}', ['hash', 'code', 'name', 'price', 'currency_id', 'available', 'weight', 'image', 'make'], $rows)->execute();
                    unset($rows);
                    $count = 0;
                }
            }
        }
        if (!$insert) {
            Yii::$app->db->createCommand()->batchInsert('{{%product}}', ['hash', 'code', 'name', 'price', 'currency_id', 'available', 'weight', 'image', 'make'], $rows)->execute();
        }
    }

    public function getAccessTokenName()
    {
        return $this->_accessTokenName;
    }

    public function getErrorMessage()
    {
        return $this->_errorMessage;
    }

    public function getSuccessMessage()
    {
        return $this->_successMessage;
    }

    public function getFileNameParam()
    {
        return $this->_fileNameParam;
    }

    public function getParsePath()
    {
        return Yii::$app->session->get('parsePath');
    }

    public function getParseFileName()
    {
        return Yii::$app->session->get('parseFileName');
    }

    public function setParsePath($value)
    {
        Yii::$app->session->set('parsePath', $value);
    }

    public function setParseFileName($value)
    {
        Yii::$app->session->set('parseFileName', $value);
    }

    protected function initExchange()
    {
        $headers = Yii::$app->request->headers;
        $name = $this->getAccessTokenName();
        if ($headers->has($name)) {
            $accessToken = $headers->get($name);
        } elseif (Yii::$app->request->get($name)) {
            $accessToken = Yii::$app->request->get($name);
        } else {
            $cookies = Yii::$app->request->cookies;
            if ($cookies->getValue($name)) {
                $accessToken = $cookies->getValue($name);
            } else {
                echo $this->getErrorMessage() . "\n";
                return;
            }
        }
        $user = ApiUser::findByAccessToken($accessToken);
        if ($user && $user->validateAccessToken($accessToken)) {
            Yii::error('init was ok');
            Yii::getLogger()->flush(true);
            echo "zip=yes\nfile_limit=1000000\n";
        } else {
            echo $this->getErrorMessage() . "\n";
        }
    }
}
