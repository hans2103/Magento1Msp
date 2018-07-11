<?php

require_once dirname(__FILE__) . "/Core.php";

class Object_Qwindo extends Object_Core
{

    public $success;
    public $data;

    public function delete($body, $endpoint = '')
    {
        $result = parent::delete(json_encode($body), $endpoint);
        $this->success = $result->success;
        return $result;
    }

    public function put($body, $endpoint = 'qwindo')
    {
        $result = parent::put($body, $endpoint);
        $this->success = $result->success;
        return $result;
    }

    public function post($body, $endpoint = 'qwindo')
    {
        $result = parent::post($body, $endpoint);
        $this->success = $result->success;
        //$this->data = $result->data;
        return $this->data;
    }

}
