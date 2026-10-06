import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'

// Execute the actual published template hook, not a second copy of its logic.
const template = readFileSync(new URL('../../resources/views/vendor/l5-swagger/index.blade.php', import.meta.url), 'utf8')
const source = template.match(/requestInterceptor: (async function\(request\) \{[\s\S]*?\n            \}),/)?.[1]
assert.ok(source, 'Swagger request interceptor must be present')
const createInterceptor = (fetch, document) => new Function('fetch', 'document', `return (${source})`)(fetch, document)

test('safe requests include credentials without a CSRF refresh', async () => {
  const intercept = createInterceptor(() => { throw new Error('Unexpected fetch') }, { cookie: '' })
  assert.deepEqual(await intercept({ method: 'GET' }), { method: 'GET', credentials: 'include' })
})
test('mutations initialize cookies and use the freshly decoded token', async () => {
  const document = { cookie: 'XSRF-TOKEN=old' }, calls = []
  const intercept = createInterceptor(async (...args) => {
    calls.push(args)
    document.cookie = 'session=example; XSRF-TOKEN=new%2Btoken%3D'
    return { ok: true }
  }, document)
  for (const method of ['POST', 'PUT', 'PATCH', 'DELETE']) {
    const request = await intercept({ method, headers: { Accept: 'application/json' } })
    assert.equal(request.headers['X-XSRF-TOKEN'], 'new+token=')
    assert.equal(request.headers.Accept, 'application/json')
  }
  assert.equal(calls.length, 4)
  assert.equal(calls[0][0], '/sanctum/csrf-cookie')
  assert.equal(calls[0][1].credentials, 'include')
})
test('failed CSRF preparation rejects the mutation', async () => {
  const intercept = createInterceptor(async () => ({ ok: false }), { cookie: 'XSRF-TOKEN=old' })
  await assert.rejects(intercept({ method: 'POST' }), /initialize CSRF/)
})
test('a missing CSRF cookie rejects the mutation', async () => {
  const intercept = createInterceptor(async () => ({ ok: true }), { cookie: 'session=example' })
  await assert.rejects(intercept({ method: 'POST' }), /CSRF cookie unavailable/)
})
test('malformed CSRF encoding fails instead of sending a guessed token', async () => {
  const intercept = createInterceptor(async () => ({ ok: true }), { cookie: 'XSRF-TOKEN=%bad%' })
  await assert.rejects(intercept({ method: 'POST' }), URIError)
})
