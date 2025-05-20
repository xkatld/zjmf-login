<?php

// 文件路径: modules/oauth/github/github.php

namespace oauth\github;

/**
 * GitHub第三方登录插件
 */
class github
{
    /**
     * 插件信息函数
     * @return array
     */
    public function meta()
    {
        return [
            'name'        => 'GitHub登录',
            'description' => '使用GitHub账号登录您的网站',
            'author'      => 'xkatld',
            'logo_url'    => 'github.svg',
        ];
    }

    /**
     * 插件接口配置信息函数
     * @return array
     */
    public function config()
    {
        return [
            'Client ID' => [
                'type' => 'text',
                'name' => 'client_id',
                'desc' => '从GitHub OAuth Apps或GitHub Apps获取的Client ID'
            ],
            'Client Secret' => [
                'type' => 'text',
                'name' => 'client_secret',
                'desc' => '从GitHub OAuth Apps或GitHub Apps获取的Client Secret'
            ],
        ];
    }

    /**
     * 生成授权地址函数
     * @param array $params
     * @return string
     * @throws \Exception
     */
    public function url($params)
    {
        if (empty($params['client_id']) || empty($params['callback'])) {
            throw new \Exception("GitHub OAuth configuration error: client_id or callback URL is missing.");
        }

        $clientId = $params['client_id'];
        $redirectUri = $params['callback'];
        $scope = 'read:user'; // Basic scope to get public user info and ID

        $authUrl = 'https://github.com/login/oauth/authorize?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            // 'state' => $params['state'] ?? 'your_generated_state', // 实际应用中强烈建议使用 state 参数
        ]);

        return $authUrl;
    }

    /**
     * 回调地址处理函数
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function callback($params)
    {
        if (empty($params['client_id']) || empty($params['client_secret']) || empty($params['code'])) {
             throw new \Exception("GitHub OAuth callback error: Missing client_id, client_secret, or code.");
        }

        $clientId = $params['client_id'];
        $clientSecret = $params['client_secret'];
        $redirectUri = $params['callback'];
        $code = $params['code'];

        $tokenUrl = 'https://github.com/login/oauth/access_token';
        $tokenParams = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        $headers = [
            'Accept: application/json' // 请求 JSON 格式
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("Failed to get GitHub access token. HTTP Code: " . $httpCode . ", Error: " . $error);
        }

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['access_token'])) {
            $errorMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? 'Unknown error';
            throw new \Exception("GitHub access token not found in response: " . $errorMsg . " | Response: " . $response);
        }

        $accessToken = $tokenData['access_token'];

        $userInfoUrl = 'https://api.github.com/user';
        $headers = [
            'Authorization: token ' . $accessToken,
            'User-Agent: ' . ($this->meta()['author'] ?? 'FinancialSystemOAuthPlugin'), // GitHub API requires a User-Agent header
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("Failed to get GitHub user info. HTTP Code: " . $httpCode . ", Error: " . $error);
        }

        $userData = json_decode($response, true);

        if (!isset($userData['id'])) {
             $errorMsg = $userData['message'] ?? 'Unknown error';
             throw new \Exception("GitHub user ID not found in response: " . $errorMsg . " | Response: " . $response);
        }

        $openid = (string) $userData['id'];
        $userInfo = [
            'username' => $userData['login'] ?? $userData['name'] ?? 'GitHub User',
            'sex'      => null,
            'province' => $userData['location'] ?? null,
            'city'     => null,
            'avatar'   => $userData['avatar_url'] ?? null,
        ];

        $callbackBind = 'all';

        return [
            'openid'       => $openid,
            'data'         => $userInfo,
            'callbackBind' => $callbackBind,
        ];
    }
}
