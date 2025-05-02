<?php
namespace Waaiy;

use GuzzleHttp\Client;

class API
{
    public $public_key = "";
    public $private_key = "";
    public $user_key = "";

    private $_client;
    public $page_prefix = "page";
    public $blog_category_prefix = "blogs";
    public $product_category_prefix = "products";

    public $lang_short_name="TR";

    public function __construct($base_url)
    {
        $this->_client = new Client(["base_uri"=>$base_url ,"verify"=>false]);
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
                "slug" => $slug,
                "lang_short_name"=>$this->lang_short_name
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                $response->data->_PHOTOS=json_decode(base64_decode($response->data->_PHOTOS));
                $response->data->_VIDEOS=json_decode(base64_decode($response->data->_VIDEOS));
                $response->data->_FILES=json_decode(base64_decode($response->data->_FILES));
                $response->data->_ACORDIONS=json_decode(base64_decode($response->data->_ACORDIONS));
                $response->data->_TABS=json_decode(base64_decode($response->data->_TABS));
                $response->data->_FIELDS=json_decode(base64_decode($response->data->_FIELDS));
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
                "slug" => $slug,
                "lang_short_name"=>$this->lang_short_name
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                $response->data->_PHOTOS=json_decode(base64_decode($response->data->_PHOTOS));
                $response->data->_VIDEOS=json_decode(base64_decode($response->data->_VIDEOS));
                $response->data->_FILES=json_decode(base64_decode($response->data->_FILES));
                $response->data->_ACORDIONS=json_decode(base64_decode($response->data->_ACORDIONS));
                $response->data->_TABS=json_decode(base64_decode($response->data->_TABS));
                $response->data->_FIELDS=json_decode(base64_decode($response->data->_FIELDS));
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
                "slug" => $slug,
                "lang_short_name"=>$this->lang_short_name
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                $response->data->_PHOTOS=json_decode(base64_decode($response->data->_PHOTOS));
                $response->data->_VIDEOS=json_decode(base64_decode($response->data->_VIDEOS));
                $response->data->_FILES=json_decode(base64_decode($response->data->_FILES));
                $response->data->_ACORDIONS=json_decode(base64_decode($response->data->_ACORDIONS));
                $response->data->_TABS=json_decode(base64_decode($response->data->_TABS));
                $response->data->_FIELDS=json_decode(base64_decode($response->data->_FIELDS));
                return $response->data;
            } else {
                throw new \Exception($response->message);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function blogcategory($slug, $page, $limit)
    {
        try {
            $response = $this->_client->request("POST",  "/blogcategory", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ],"query"=>[
                "page" => $page,
            ], "form_params" => [
                "category_slug" => $slug,
                "limit" => $limit,
                "lang_short_name" => $this->lang_short_name,
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                $response->data->_BLOGS=array_map(function($item) {
                    $item->_PHOTOS=json_decode(base64_decode($item->_PHOTOS));
                    $item->_VIDEOS=json_decode(base64_decode($item->_VIDEOS));
                    $item->_FILES=json_decode(base64_decode($item->_FILES));
                    $item->_ACORDIONS=json_decode(base64_decode($item->_ACORDIONS));
                    $item->_TABS=json_decode(base64_decode($item->_TABS));
                    $item->_FIELDS=json_decode(base64_decode($item->_FIELDS));
                    return $item;
                },$response->data->_BLOGS);
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
            ],"query"=>[
                "page" => $page,
            ], "form_params" => [
                "category_slug" => $slug,
                "limit" => $limit,
                "lang_short_name" => $lang_short_name,
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {

                $response->data->_PRODUCTS=array_map(function($item) {
                    $item->_PHOTOS=json_decode(base64_decode($item->_PHOTOS));
                    $item->_VIDEOS=json_decode(base64_decode($item->_VIDEOS));
                    $item->_FILES=json_decode(base64_decode($item->_FILES));
                    $item->_ACORDIONS=json_decode(base64_decode($item->_ACORDIONS));
                    $item->_TABS=json_decode(base64_decode($item->_TABS));
                    $item->_FIELDS=json_decode(base64_decode($item->_FIELDS));
                    return $item;
                },$response->data->_PRODUCTS);
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
            ],"query"=>[
                "page" => $page,
            ],"form_params" => [
                "slug" => $slug,
                "limit" => $limit
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
            $response = $this->_client->request("POST",  "/media", ['headers' => [
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
