#!/usr/bin/env bash
# Writes .wp-env.override.json, and the same for the integration site (.wp-env.tests.override.json), for the weekly canary (docs/AUDIT.md: Compatibility): WordPress trunk and WooCommerce's
# newest pre-release when there is one, else its newest release. Run before `wp-env start`; `npm run env:newest` removes it.
set -euo pipefail
cd "$(dirname "$0")/.."
wc="$(curl -sfg 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=woocommerce&request[fields][versions]=1' \
	| node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{const v=Object.keys(JSON.parse(s).versions).filter(x=>/^\d+\.\d+\.\d+(-[a-z]+\.?\d*)?$/i.test(x));const pre=v.filter(x=>x.includes("-")).sort(cmp);const rel=v.filter(x=>!x.includes("-")).sort(cmp);const pick=pre.length&&cmp(pre.at(-1),rel.at(-1))>0?pre.at(-1):rel.at(-1);console.log(pick);function cmp(a,b){const pa=a.split(/[.-]/),pb=b.split(/[.-]/);for(let i=0;i<Math.max(pa.length,pb.length);i++){const x=pa[i]??"",y=pb[i]??"";const nx=Number(x),ny=Number(y);if(!isNaN(nx)&&!isNaN(ny)){if(nx!==ny)return nx-ny;}else if(x!==y)return x<y?-1:1;}return 0;}})')"
cat > .wp-env.override.json <<JSON
{
	"core": "WordPress/WordPress#master",
	"phpVersion": "8.5",
	"mappings": {
		"wp-content/plugins/woocommerce": "https://downloads.wordpress.org/plugin/woocommerce.${wc}.zip"
	}
}
JSON
cp .wp-env.override.json .wp-env.tests.override.json
echo "The override files are written (WordPress trunk, WooCommerce ${wc}, PHP 8.5). Run npm run env:start."
