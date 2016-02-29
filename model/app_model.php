<?php
/**
 * Application model for Cake.
 *
 * This is a placeholder class.
 * Create the same file in app/app_model.php
 * Add your application-wide methods to the class, your models will inherit them.
 *
 * @package       cake
 * @subpackage    cake.cake.libs.model
 */
class AppModel extends Model {

    /**
     * afterFind
     * contain使用した場合に子のafterFindが呼ばれない対策
     * @param mixed $results
     * @param bool $primary
     * @return mixed
     * @link http://d.hatena.ne.jp/okomeworld/20110131/1296440459
     */
    function afterFind($results,$primary=false)
    {
        $results = parent::afterFind($results,$primary);

        if(!$primary) {
            $params = array($results,$primary);
            $options = array('modParams' => true);
            $results = $this->Behaviors->trigger($this,'afterFind',$params,$options);
        }
        return $results;
    }

}
