import axios from 'axios';
window.axios = axios;

/**
 * LaravelのJSONリクエストで共通利用するAxiosヘッダーを設定する。
 */
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common.Accept = 'application/json';

const csrfToken = document.querySelector('meta[name="csrf-token"]');

if (csrfToken instanceof HTMLMetaElement) {
    /**
     * BladeのmetaタグからCSRFトークンを取得してAxiosに設定する。
     */
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}
