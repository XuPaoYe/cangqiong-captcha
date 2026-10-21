from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont
from fontTools import subset
from pathlib import Path
import re,hashlib,sys
root=Path(__file__).resolve().parents[2]
source=Path(sys.argv[1])
if hashlib.sha256(source.read_bytes()).hexdigest()!='a3041811a78c361b1de50f953c805e0244951c21c5bd412f7232ef0d899af0da':
    raise SystemExit('Noto Sans SC source hash mismatch')
font=instantiateVariableFont(TTFont(source),{'wght':500},inplace=True)
text=re.search("preg_split\\('//u', '([^']+)'",(root/'src/ImageCaptcha.php').read_text()).group(1)
text += '23456789ACDEFHJKMNPRTUVWXY'
options=subset.Options();options.name_IDs=['*'];options.name_languages=['*'];options.name_legacy=True
subsetter=subset.Subsetter(options=options);subsetter.populate(text=text);subsetter.subset(font)
for row in font['name'].names:
 values={1:'Foundation Captcha Sans',2:'Medium',3:'FoundationCaptchaSans-Medium-20261011',4:'Foundation Captcha Sans Medium',6:'FoundationCaptchaSans-Medium',16:'Foundation Captcha Sans',17:'Medium'}
 if row.nameID in values:row.string=values[row.nameID].encode(row.getEncoding())
target=root/'resources/fonts/FoundationCaptchaSans-Medium.ttf'
font.save(target)
print('source_sha256',hashlib.sha256(source.read_bytes()).hexdigest())
print('subset_sha256',hashlib.sha256(target.read_bytes()).hexdigest(),target.stat().st_size,'bytes')
print('pool',text)
