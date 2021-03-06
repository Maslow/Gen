<?php

namespace app\modules\admin\models;

use app\modules\auth\models\U;
use Yii;
use yii\base\ErrorException;
use yii\db\Exception;

/**
 * This is the model class for table "{{%administrator}}".
 *
 * @property integer $uid
 * @property string $username
 * @property string $password
 * @property integer $created_at
 * @property string $created_ip
 * @property integer $created_by
 * @property U $u
 */
class Administrator extends \yii\db\ActiveRecord
{
    const U_SOURCE = 'administrator';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%administrator}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['username', 'password', 'created_at', 'created_ip', 'created_by'], 'required'],
            [['uid', 'created_at', 'created_by'], 'integer'],
            [['username'], 'string', 'min' => 3, 'max' => 32],
            [['password'], 'string', 'min' => 6, 'max' => 64],
            [['created_ip'], 'string', 'max' => 16],
            [['username'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'uid' => 'Uid',
            'username' => 'Username',
            'password' => 'Password',
            'created_at' => 'Created At',
            'created_ip' => 'Created Ip',
            'created_by' => 'Created By',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getU()
    {
        return $this->hasOne(U::className(), ['id' => 'uid']);
    }

    /**
     * @return \yii\rbac\Role[]
     */
    public function getRole()
    {
        $roles = Yii::$app->authManager->getRolesByUser($this->uid);
        if ($roles)
            return array_values($roles)[0];
        return null;
    }

    /**
     * @return $this
     */
    public function getAuthor()
    {
        return self::find()->where(['uid' => $this->created_by]);
    }

    public function fields()
    {
        return [
            'uid',
            'username',
            'created_at',
            'created_ip',
            'created_by',
            'role',
            'author'
        ];
    }

    public function beforeValidate()
    {
        if ($this->isNewRecord) {
            $this->created_at = time();
            $this->created_ip = isset(Yii::$app->request->userIP) ? Yii::$app->request->userIP : '0.0.0.0';
            if (isset(Yii::$app->user) && !Yii::$app->user->isGuest)
                $this->created_by = Yii::$app->user->id;
            else
                $this->created_by = 0;
        }

        return parent::beforeValidate();
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $u = new U();
            $u->source = self::U_SOURCE;
            if (!$u->save()) {
                $this->addError('uid', 'Failed to create uid for unknown reason');
                return false;
            }
            $this->uid = $u->id;

            $this->password = Yii::$app->security->generatePasswordHash($this->password);
        } elseif (array_key_exists('password', $this->getDirtyAttributes())) {
            $this->password = Yii::$app->security->generatePasswordHash($this->password);
        }
        return parent::beforeSave($insert);
    }

    public function afterDelete()
    {
        if ($u = U::findOne($this->uid)) {
            $u->delete();
        } else {
            throw new Exception('Delete u by uid failed for no reason');
        }
        parent::afterDelete();
    }
}
