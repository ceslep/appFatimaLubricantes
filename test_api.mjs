import https from 'https';

function testEndpoint(action) {
  return new Promise((resolve, reject) => {
    const url = new URL(`https://app.iedeoccidente.com/appf/lubricantes_api.php?action=${action}`);
    const req = https.request({
      hostname: url.hostname,
      path: url.pathname + url.search,
      method: 'GET',
    }, res => {
      let data = '';
      res.on('data', c => data += c);
      res.on('end', () => {
        console.log(`\n=== ${action} ===`);
        try {
          const parsed = JSON.parse(data);
          if (parsed.data && Array.isArray(parsed.data)) {
            parsed.data.slice(0, 2).forEach(item => console.log(JSON.stringify(item)));
          } else {
            console.log(JSON.stringify(parsed));
          }
        } catch {
          console.log('Raw:', data.substring(0, 500));
        }
        resolve();
      });
    });
    req.on('error', e => { console.error('Error:', e.message); reject(e); });
    req.end();
  });
}

(async () => {
  await testEndpoint('listar_ventas');
})();
