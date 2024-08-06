<?php

namespace oauth\gitee;

class gitee
{
    public function meta()
    {
        return [
            'name'        => 'Gitee登录',
            'description' => '使用Gitee账号登录',
            'author'      => 'xkatld',
            'logo_url'    => 'gitee.svg',
        ];
    }

    public function config()
    {
        return [
            'Client ID' => [
                'type' => 'text',
                'name' => 'client_id',
                'desc' => 'Gitee OAuth应用的Client ID'
            ],
            'Client Secret' => [
                'type' => 'text',
                'name' => 'client_secret',
                'desc' => 'Gitee OAuth应用的Client Secret'
            ],
        ];
    }

    public function url($params)
    {
        $clientId = $params['client_id'];
        $redirectUri = urlencode($params['callback']);
        $state = md5(uniqid(rand(), true));

        $authUrl = "https://gitee.com/oauth/authorize?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&scope=user_info&state={$state}";

        return $authUrl;
    }

    public function callback($params)
    {
        $clientId = $params['client_id'];
        $clientSecret = $params['client_secret'];
        $code = $params['code'] ?? '';

        if (empty($code)) {
            throw new \Exception('授权失败，请重试');
        }

        // 获取访问令牌
        $tokenUrl = "https://gitee.com/oauth/token";
        $postData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $params['callback'],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['access_token'])) {
            throw new \Exception('获取访问令牌失败');
        }

        $accessToken = $tokenData['access_token'];

        // 获取用户信息
        $userInfoUrl = "https://gitee.com/api/v5/user?access_token=" . $accessToken;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $userResponse = curl_exec($ch);
        curl_close($ch);

        $userData = json_decode($userResponse, true);

        if (!isset($userData['id'])) {
            throw new \Exception('获取用户信息失败');
        }

        return [
            'openid' => $userData['id'],
            'data' => [
                'username' => $userData['name'],
                'sex' => '', // Gitee不提供性别信息
                'province' => '', // Gitee不提供省份信息
                'city' => '', // Gitee不提供城市信息
                'avatar' => $userData['avatar_url'],
            ],
            'callbackBind' => 'all',
        ];
    }
}
