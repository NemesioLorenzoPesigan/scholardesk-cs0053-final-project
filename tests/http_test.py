"""Black-box API checks against a real PHP server and a temporary database."""
from contextlib import contextmanager
import uuid
import http.cookiejar
import json
import os
from pathlib import Path
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

root = Path(__file__).resolve().parents[1]
php = os.environ.get('PHP_EXE', r'C:\xampp\php\php.exe')
@contextmanager
def isolated_database():
    directory = root/'data'/('http-test-'+uuid.uuid4().hex)
    directory.mkdir()
    try:
        yield directory
    finally:
        for file in directory.iterdir():
            file.unlink()
        directory.rmdir()

with isolated_database() as temporary:
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
    environment = {**os.environ, 'SCHOLARDESK_DB': str(Path(temporary) / 'test.sqlite')}
    log = open(Path(temporary) / 'server.log', 'w')
    server = subprocess.Popen([php, '-S', f'127.0.0.1:{port}', '-t', str(root/'public')], cwd=root, env=environment, stdout=log, stderr=log, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    base = f'http://127.0.0.1:{port}/api.php?route='
    token = ''
    checks = 0

    def request(route, method='GET', data=None, csrf=True, raw=None, content_type='application/json'):
        headers = {'Content-Type': content_type}
        if csrf:
            headers['X-CSRF-Token'] = token
        payload = raw if raw is not None else json.dumps(data).encode() if data is not None else None
        req = urllib.request.Request(base+route, data=payload, headers=headers, method=method)
        try:
            response = client.open(req, timeout=5)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, json.loads(response.read())

    def verify(name, condition):
        global checks
        if not condition:
            raise AssertionError(name)
        checks += 1
        print('PASS | '+name)

    try:
        for attempt in range(40):
            try:
                status, body = request('session')
                token = body['data']['csrf_token']
                break
            except (urllib.error.URLError, ConnectionError):
                time.sleep(.1)
        else:
            raise RuntimeError('PHP test server failed to start')
        verify('Session endpoint returns CSRF token', status == 200 and len(token) == 48)
        student = dict(student_number='HTTP-TEST', full_name='Taylor HTTP', email='http@example.test', course='BSCS', year_level=2, section='SE31')
        verify('Write without CSRF token is blocked', request('students','POST',student,csrf=False)[0] == 403)
        verify('Malformed JSON rejected', request('students','POST',raw=b'{broken')[0] == 422)
        verify('Wrong content type rejected', request('students','POST',student,content_type='text/plain')[0] == 415)
        verify('Blank required fields rejected', request('students','POST',{})[0] == 422)
        verify('Invalid email rejected', request('students','POST',{**student,'email':'bad'})[0] == 422)
        status, body = request('students','POST',student)
        verify('POST creates student with 201', status == 201)
        sid = body['data']['id']
        verify('GET retrieves new student', request(f'students&id={sid}')[1]['data']['full_name'] == 'Taylor HTTP')
        verify('Duplicate student rejected with 409', request('students','POST',student)[0] == 409)
        verify('GET search finds student', len(request('students&q=HTTP-TEST')[1]['data']) == 1)
        verify('Array query rejected', request('students&q[]=bad')[0] == 422)
        verify('Negative ID rejected', request('students&id=-1')[0] == 422)
        verify('Unknown ID returns 404', request('students&id=99999')[0] == 404)
        verify('PUT updates student', request(f'students&id={sid}','PUT',{**student,'full_name':'Taylor Updated'})[1]['data']['full_name'] == 'Taylor Updated')
        task = dict(title='HTTP task',subject='CS0053',due_date='2099-12-31',description='API test')
        status, body = request('tasks','POST',task)
        tid = body['data']['id']
        verify('POST creates task with 201', status == 201)
        verify('PUT updates task', request(f'tasks&id={tid}','PUT',{**task,'title':'HTTP revised'})[1]['data']['title'] == 'HTTP revised')
        verify('GET submissions combines student and task', len(request('submissions')[1]['data']) == 1)
        submission=dict(student_id=sid,task_id=tid,status='submitted')
        verify('PUT marks task submitted', request('submissions','PUT',submission)[0] == 200)
        verify('Dashboard reflects submitted work', request('dashboard')[1]['data']['completion'] == 100)
        verify('Invalid submission status rejected', request('submissions','PUT',{**submission,'status':'invalid'})[0] == 422)
        verify('PUT restores pending status', request('submissions','PUT',{**submission,'status':'pending'})[1]['data']['status'] == 'pending')
        verify('Unsupported method returns 405', request('students','PATCH')[0] == 405)
        verify('Unknown endpoint returns 404', request('missing')[0] == 404)
        verify('DELETE removes task', request(f'tasks&id={tid}','DELETE')[0] == 200)
        verify('DELETE removes student', request(f'students&id={sid}','DELETE')[0] == 200)
        verify('Deleted record is no longer available', request(f'students&id={sid}')[0] == 404)
        verify('Database file is outside public web root', urllib.request.urlopen(f'http://127.0.0.1:{port}/api.php?route=dashboard').status == 200 and not (root/'public'/'data').exists())
        print(f'\n{checks} passed; 0 failed')
    finally:
        server.terminate()
        server.wait(timeout=5)
        log.close()
