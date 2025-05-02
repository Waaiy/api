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
            $response = $this->_client->request("GET", "/settings", ['headers' => [
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
            $response = $this->_client->request("GET",  "/menuitems", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "query" => [
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
            $response = $this->_client->request("GET",  "/blog", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "query" => [
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
            $response = $this->_client->request("GET",  "/page", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "query" => [
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
            $response = $this->_client->request("GET",  "/product", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "query" => [
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
            $response = $this->_client->request("GET",  "/blogcategory", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ],"query"=>[
                "page" => $page,
                "category_slug" => $slug,
                "limit" => $limit,
                "lang_short_name" => $this->lang_short_name,
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {
                $response->data->__BLOGS=array_map(function($item) {
                    $item->_PHOTOS=json_decode(base64_decode($item->_PHOTOS));
                    $item->_VIDEOS=json_decode(base64_decode($item->_VIDEOS));
                    $item->_FILES=json_decode(base64_decode($item->_FILES));
                    $item->_ACORDIONS=json_decode(base64_decode($item->_ACORDIONS));
                    $item->_TABS=json_decode(base64_decode($item->_TABS));
                    $item->_FIELDS=json_decode(base64_decode($item->_FIELDS));
                    return $item;
                },$response->data->__BLOGS);
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
            $response = $this->_client->request("GET",  "/productcategory", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ],"query"=>[
                "page" => $page,
                "category_slug" => $slug,
                "limit" => $limit,
                "lang_short_name" => $lang_short_name,
            ]]);
            $response = json_decode($response->getBody());
            if ($response->status == 1) {

                $response->data->__PRODUCTS=array_map(function($item) {
                    $item->_PHOTOS=json_decode(base64_decode($item->_PHOTOS));
                    $item->_VIDEOS=json_decode(base64_decode($item->_VIDEOS));
                    $item->_FILES=json_decode(base64_decode($item->_FILES));
                    $item->_ACORDIONS=json_decode(base64_decode($item->_ACORDIONS));
                    $item->_TABS=json_decode(base64_decode($item->_TABS));
                    $item->_FIELDS=json_decode(base64_decode($item->_FIELDS));
                    return $item;
                },$response->data->__PRODUCTS);
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
            $response = $this->_client->request("GET",  "/blogcategories", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "query" => [
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
            $response = $this->_client->request("GET",  "/productcategpries", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "query" => [
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
            $response = $this->_client->request("GET",  "/languages", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
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
            $response = $this->_client->request("GET",  "/dataset", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ],"query"=>[
                "page" => $page,
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
            $response = $this->_client->request("GET",  "/media", ['headers' => [
                "W-User-Key" => $this->user_key,
                "W-Public-Key" => $this->public_key,
                "W-Private-Key" => $this->private_key,
            ], "query" => [
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
    public function cover_image($content,$media_url){
        if(is_string($content)){
            $content=json_decode($content);
        }
        $cover=array_map(function($item){
            if($item->is_cover==1){
                return $item;
            }
        },$content->_PHOTOS);
        return $this->media($cover[0]->medya_id,$media_url);
    }
}
