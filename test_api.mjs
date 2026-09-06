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
          console.log(JSON.stringify(parsed, null, 2).substring(0, 1500));
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
  await testEndpoint('resumen_dia');
})();
