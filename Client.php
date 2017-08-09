<?php

namespace common\models;

/**
 * This is the model class for table "client".
 *
 * @property integer $id
 * @property string $patronymic
 * @property string $birthday
 * @property string $birthday_place
 * @property string $address
 *
 * @property User $user
 * @property ClientSettings[] $clientSettings
 * @property Forecast[] $forecasts
 * @property NotificationSettings[] $notificationSettings
 */
class Client extends UserableActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'client';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['birthday_place'], 'required'],
            [['birthday'], 'safe'],
            [['patronymic'], 'string', 'max' => 45],
            [['birthday_place', 'address'], 'string', 'max' => 255],
            [['address'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'patronymic' => 'Patronymic',
            'birthday' => 'Birthday',
            'birthday_place' => 'Birthday Place',
            'address' => 'Address',
            'avatar' => 'Avatar',
            'user_id' => 'User ID',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(\common\models\User::className(), ['id' => 'user_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getClientSettings()
    {
        return $this->hasOne(ClientSettings::className(), ['client_id' => 'id']);
    }
    
    /**
     * @return \yii\db\ActiveQuery
     */
    public function getForecasts()
    {
        return $this->hasMany(Forecast::className(), ['client_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getNotificationSettings()
    {
        return $this->hasMany(NotificationSettings::className(), ['client_id' => 'id']);
    }
}
