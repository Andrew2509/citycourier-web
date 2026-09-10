// Validate openapi.yaml: YAML syntax + OpenAPI 3.0 structural checks
const fs = require('fs');
const yaml = require('js-yaml');

let doc;
try {
  doc = yaml.load(fs.readFileSync('public/docs/openapi.yaml', 'utf8'));
} catch (e) {
  console.error('YAML PARSE ERROR:');
  console.error(e.message);
  process.exit(1);
}

const errors = [];
const warnings = [];

// Top-level required fields
for (const key of ['openapi', 'info', 'paths', 'components']) {
  if (!doc[key]) errors.push(`Missing top-level: ${key}`);
}
if (!/^3\./.test(doc.openapi || '')) errors.push(`openapi version must be 3.x, got: ${doc.openapi}`);

// info.title & version
if (doc.info && !doc.info.title) errors.push('info.title missing');
if (doc.info && !doc.info.version) errors.push('info.version missing');

// Collect all $refs and verify each resolves
const refs = new Set();
function walk(node, path) {
  if (Array.isArray(node)) {
    node.forEach((v, i) => walk(v, `${path}[${i}]`));
    return;
  }
  if (node && typeof node === 'object') {
    for (const [k, v] of Object.entries(node)) {
      if (k === '$ref' && typeof v === 'string') {
        refs.add(v);
        // Verify local ref resolves
        if (v.startsWith('#/')) {
          const parts = v.slice(2).split('/');
          let cur = doc;
          for (const p of parts) {
            const decoded = p.replace(/~1/g, '/').replace(/~0/g, '~');
            if (cur == null || typeof cur !== 'object' || !(decoded in cur)) {
              errors.push(`Broken $ref at ${path}: ${v} (missing '${decoded}')`);
              break;
            }
            cur = cur[decoded];
          }
        } else {
          warnings.push(`External $ref at ${path}: ${v}`);
        }
      } else {
        walk(v, `${path}.${k}`);
      }
    }
  }
}
walk(doc, '$');

// Verify every operation has responses + tags
const httpMethods = new Set(['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace']);
let opCount = 0;
const tagsUsed = new Set();
for (const [p, pathItem] of Object.entries(doc.paths || {})) {
  if (typeof pathItem !== 'object' || pathItem === null) continue;
  for (const [m, op] of Object.entries(pathItem)) {
    if (!httpMethods.has(m)) continue;
    opCount++;
    if (!op || typeof op !== 'object') { errors.push(`${m.toUpperCase()} ${p}: not an operation object`); continue; }
    if (!op.responses || Object.keys(op.responses).length === 0) errors.push(`${m.toUpperCase()} ${p}: missing responses`);
    if (!op.tags || op.tags.length === 0) warnings.push(`${m.toUpperCase()} ${p}: no tags`);
    else op.tags.forEach((t) => tagsUsed.add(t));
    for (const [code, resp] of Object.entries(op.responses || {})) {
      if (!/^[1-5](\d\d|XX)$/.test(code)) errors.push(`${m.toUpperCase()} ${p}: invalid response code '${code}'`);
      if (resp && resp.$ref) refs.add(resp.$ref);
    }
  }
}

// Verify declared tags cover used tags
const declaredTags = new Set((doc.tags || []).map((t) => t.name));
for (const t of tagsUsed) {
  if (!declaredTags.has(t)) errors.push(`Tag '${t}' used in operations but not declared in top-level tags`);
}

// Security scheme refs
if (doc.components && doc.components.securitySchemes) {
  for (const sec of doc.security || []) {
    for (const name of Object.keys(sec)) {
      if (!doc.components.securitySchemes[name]) errors.push(`security scheme '${name}' referenced but not declared`);
    }
  }
}

// Stats
const pathCount = Object.keys(doc.paths || {}).length;
console.log(`OK: YAML parses. ${pathCount} paths, ${opCount} operations, ${refs.size} unique $refs.`);
console.log(`Tags declared: ${[...declaredTags].join(', ')}`);
if (warnings.length) {
  console.log(`\nWarnings (${warnings.length}):`);
  warnings.slice(0, 20).forEach((w) => console.log('  - ' + w));
}
if (errors.length) {
  console.error(`\nERRORS (${errors.length}):`);
  errors.forEach((e) => console.error('  - ' + e));
  process.exit(1);
}
console.log('\nAll structural checks passed.');
