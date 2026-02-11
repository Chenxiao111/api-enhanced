# api/hb.py
from http.server import BaseHTTPRequestHandler
import json
import requests
from urllib.parse import urlparse, parse_qs

class handler(BaseHTTPRequestHandler):
    def do_GET(self):
        # 1. 解析参数
        query = parse_qs(urlparse(self.path).query)
        song_name = query.get('name', [None])[0]

        if not song_name:
            self.send_response(400)
            self.end_headers()
            self.wfile.write(json.dumps({"code": 400, "msg": "Missing name parameter"}).encode())
            return

        # 这里的 BASE_URL 指向你自己的 API 域名
        # 因为在同一个项目下，可以用 localhost 或者直接写完整域名
        BASE_URL = "https://api-yy.vercel.app" 

        try:
            # 2. 搜索歌曲 (调用 api-enhanced 的接口)
            search_url = f"{BASE_URL}/search?keywords={song_name}&limit=1"
            search_data = requests.get(search_url, timeout=5).json()
            
            if search_data.get('code') != 200 or not search_data['result']['songs']:
                raise Exception("Song not found")

            song = search_data['result']['songs'][0]
            song_id = song['id']

            # 3. 获取播放链接
            url_api = f"{BASE_URL}/song/url/v1?id={song_id}&level=standard"
            url_data = requests.get(url_api, timeout=5).json()
            music_url = url_data['data'][0]['url']

            # 4. 构造黄白助手要求的格式
            result = {
                "code": 200,
                "title": song['name'],
                "singer": song['ar'][0]['name'],
                "cover": song['al']['picUrl'],
                "link": f"https://music.163.com/#/song?id={song_id}",
                "music_url": music_url
            }

            self.send_response(200)
            self.send_header('Content-type', 'application/json; charset=utf-8')
            self.end_headers()
            self.wfile.write(json.dumps(result, ensure_ascii=False).encode('utf-8'))

        except Exception as e:
            self.send_response(200) # 即使失败也返回200，方便调试看报错
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({"code": 500, "msg": str(e)}).encode())
