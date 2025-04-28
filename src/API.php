<?php
namespace Waaiy;

use GuzzleHttp\Client;

class API
{
    public $base_url = "https://api.waaiy.dev/";
    public $public_key = "";
    public $private_key = "";
    public $user_key = "";

    private $_client;
    public $page_prefix = "page";
    public $blog_category_prefix = "blogs";
    public $product_category_prefix = "products";

    public $lang_short_name="TR";

    public function __construct()
    {
        $this->_client = new Client(["base_uri"=>$this->base_url ,"verify"=>false]);
    }

    public function adjust($ayar_slug)
    {
        try {
            $response = $this->_client->request("POST", "/settings", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "form_params" => [
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                return $response->data->{$ayar_slug};
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function menuitems($ayar_slug)
    {
        try {
            $response = $this->_client->request("POST",  "/menuitems", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "form_params" => [
                "slug" => $ayar_slug,
                "lang_short_name" => $this->lang_short_name,
                "p_prefix" => $this->page_prefix,
                "bc_prefix" => $this->blog_category_prefix,
                "pc_prefix" => $this->product_category_prefix,
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                return $response->data;
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function blog($slug)
    {
        try {
            $response = $this->_client->request("POST",  "/blog", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "form_params" => [
                "slug" => $slug
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                return $response->data;
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function page($slug)
    {
        try {
            $response = $this->_client->request("POST",  "/page", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "form_params" => [
                "slug" => $slug
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                return $response->data;
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function product($slug)
    {
        try {
            $response = $this->_client->request("POST",  "/product", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "form_params" => [
                "slug" => $slug
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                return $response->data;
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function blogcategory($slug, $page, $limit, $lang_short_name)
    {
        try {
            $response = $this->_client->request("POST",  "/blogcategory", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "form_params" => [
                "category_slug" => $slug,
                "page" => $page,
                "limit" => $limit,
                "lang_short_name" => $lang_short_name,
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                return $response->data;
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function productcategory($slug, $page, $limit, $lang_short_name)
    {
        try {
            $response = $this->_client->request("POST",  "/productcategory", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "form_params" => [
                "category_slug" => $slug,
                "page" => $page,
                "limit" => $limit,
                "lang_short_name" => $lang_short_name,
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                return $response->data;
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function blogcategories($parent_slug = "")
    {
        try {
            $response = $this->_client->request("POST",  "/blogcategories", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "form_params" => [
                "slug" => $parent_slug
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                return $response->data;
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function productcategories($parent_slug = "")
    {
        try {
            $response = $this->_client->request("POST",  "/productcategpries", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "form_params" => [
                "slug" => $parent_slug
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                return $response->data;
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function languages($parent_slug = "")
    {
        try {
            $response = $this->_client->request("POST",  "/languages", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "form_params" => [

            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                return $response->data;
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function dataset($slug, $limit, $page)
    {
        try {
            $response = $this->_client->request("POST",  "/dataset", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "form_params" => [
                "slug" => $slug,
                "limit" => $limit,
                "page" => $page
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                return $response->data;
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function media($id, $url)
    {
        try {
            $response = $this->_client->request("POST",  "/languages", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "form_params" => [
                "id" => $id,
                "url" => $url,
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                return $response->data;
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
