<?php

namespace common\models;

/**
 * This is the model abstract class for table userable ActiveRecord.
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string $avatar
 *
 * @property User $user
 */
abstract class UserableActiveRecord extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return array_merge(parent::rules(),[
            [['user_id'], 'integer'],
            [['first_name', 'last_name'], 'string', 'max' => 45],
            [['avatar'], 'string', 'max' => 255],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ]);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }
    
    /**
     * Returns full user name
     * 
     * @return string full user name
     */
    public function getName() 
    {
        return $this->last_name . " " . $this->first_name;
    }
}
