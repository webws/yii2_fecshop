<?php

/*
 * FecShop file.
 *
 * @link http://www.fecshop.com/
 * @copyright Copyright (c) 2016 FecShop Software LLC
 * @license http://www.fecshop.com/license/
 */

namespace fecshop\services\product;

//use fecshop\models\mongodb\product\Favorite as FavoriteModel;
use fecshop\services\Service;
use Yii;

/**
 * Product Favorite Services
 * @author Terry Zhao <2358269014@qq.com>
 * @since 1.0
 */
class Favorite extends Service
{
    protected $_favoriteModelName = '\fecshop\models\mongodb\product\Favorite';

    protected $_favoriteModel;
    
    public function init()
    {
        parent::init();
        list($this->_favoriteModelName, $this->_favoriteModel) = \Yii::mapGet($this->_favoriteModelName);
    }
    
    protected function actionGetPrimaryKey()
    {
        return '_id';
    }

    protected function actionGetByPrimaryKey($val)
    {
        $one = $this->_favoriteModel->findOne($val);
        if ($one[$this->getPrimaryKey()]) {
            return $one;
        } else {
            return new $this->_favoriteModelName();
        }
    }

    /**
     * @property $product_id | String ， 产品id
     * @property $user_id | Int ，用户id
     * @return $this->_favoriteModel ，如果用户在该产品收藏，则返回相应model。
     */
    protected function actionGetByProductIdAndUserId($product_id, $user_id = '')
    {
        if (!$user_id) {
            $identity = Yii::$app->user->identity;
            $user_id = $identity['id'];
        }
        if ($user_id) {
            $one = $this->_favoriteModel->findOne([
                'product_id' => $product_id,
                'user_id'     => $user_id,
            ]);
            if ($one[$this->getPrimaryKey()]) {
                return $one;
            }
        }
    }

    /**
     * @property $product_id | String ， 产品id
     * @property $user_id | Int ，用户id
     * @return boolean，用户收藏该产品时，执行的操作。
     */
    protected function actionAdd($product_id, $user_id)
    {
        $user_id = (int) $user_id;
        $productPrimaryKey = Yii::$service->product->getPrimaryKey();
        $product = Yii::$service->product->getByPrimaryKey($product_id);
        // 检查产品是否存在，如果不存在，输出报错信息。
        if (!isset($product[$productPrimaryKey])) {
            Yii::$service->helper->errors->add('product is not exist!');

            return;
        }
        //echo $product_id;exit;
        $favoritePrimaryKey = Yii::$service->product->favorite->getPrimaryKey();
        $one = $this->_favoriteModel->findOne([
            'product_id' => $product_id,
            'user_id'     => $user_id,
        ]);
        if (isset($one[$favoritePrimaryKey])) {
            $one->updated_at = time();
            $one->store = Yii::$service->store->currentStore;
            $one->save();

            return true;
        }
        $one = new $this->_favoriteModelName();
        $one->product_id = $product_id;
        $one->user_id = $user_id;
        $one->created_at = time();
        $one->updated_at = time();
        $one->store = Yii::$service->store->currentStore;
        $one->save();
        // 更新该用户总的收藏产品个数到用户表
        $this->updateUserFavoriteCount($user_id);
        $this->updateProductFavoriteCount($product_id);

        return true;
    }

    /**
     * @property $product_id | String
     * 更新该产品被收藏的总次数。
     */
    protected function updateProductFavoriteCount($product_id)
    {
        if ($product_id) {
            $count = $this->_favoriteModel->find()->where(['product_id'=>$product_id])->count();
            $product = Yii::$service->product->getByPrimaryKey($product_id);
            if ($product['_id']) {
                $product->favorite_count = $count;
                $product->save();
            }
        }
    }

    /**
     * @property $user_id | Int
     * 更新该用户总的收藏产品个数到用户表
     */
    protected function updateUserFavoriteCount($user_id = '')
    {
        $identity = Yii::$app->user->identity;
        if (!$user_id) {
            $user_id = $identity['id'];
        }
        if ($user_id) {
            $count = $this->_favoriteModel->find()->where(['user_id'=>$user_id])->count();
            $identity->favorite_product_count = $count;
            $identity->save();
        }
    }

    /*
     * example filter:
     * [
     * 		'numPerPage' 	=> 20,
     * 		'pageNum'		=> 1,
     * 		'orderBy'	=> [$this->getPrimaryKey() => SORT_DESC, 'sku' => SORT_ASC ],
     * 		'where'			=> [
                ['>','price',1],
                ['<=','price',10]
     * 			['sku' => 'uk10001'],
     * 		],
     * 	'asArray' => true,
     * ]
     */
    protected function actionList($filter)
    {
        $query = $this->_favoriteModel->find();
        $query = Yii::$service->helper->ar->getCollByFilter($query, $filter);

        return [
            'coll' => $query->all(),
            'count'=> $query->limit(null)->offset(null)->count(),
        ];
    }

    protected function actionColl($filter)
    {
        return $this->list($filter);
    }

    /**
     * @property $favorite_id | string
     * 通过id删除favorite
     */
    protected function actionCurrentUserRemove($favorite_id)
    {
        $identity = Yii::$app->user->identity;
        $user_id = $identity['id'];

        $one = $this->_favoriteModel->findOne([
            '_id'        => new \MongoDB\BSON\ObjectId($favorite_id),
            'user_id'    => $user_id,
        ]);
        if ($one['_id']) {
            $one->delete();
            $this->updateUserFavoriteCount($user_id);
            $product_id = (string) $one['_id'];
            $this->updateProductFavoriteCount($product_id);

            return true;
        }
    }
}
