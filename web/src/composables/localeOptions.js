export const LOCALE_OPTIONS = [
  { value: 'af',    label: 'Afrikaans (af)' },
  { value: 'sq',    label: 'Albanian (sq)' },
  { value: 'ar',    label: 'Arabic (ar)' },
  { value: 'hy',    label: 'Armenian (hy)' },
  { value: 'az',    label: 'Azerbaijani (az)' },
  { value: 'eu',    label: 'Basque (eu)' },
  { value: 'be',    label: 'Belarusian (be)' },
  { value: 'bs',    label: 'Bosnian (bs)' },
  { value: 'bg',    label: 'Bulgarian (bg)' },
  { value: 'ca',    label: 'Catalan (ca)' },
  { value: 'zh',    label: 'Chinese Simplified (zh)' },
  { value: 'zh-tw', label: 'Chinese Traditional (zh-tw)' },
  { value: 'hr',    label: 'Croatian (hr)' },
  { value: 'cs',    label: 'Czech (cs)' },
  { value: 'da',    label: 'Danish (da)' },
  { value: 'nl',    label: 'Dutch (nl)' },
  { value: 'en',    label: 'English (en)' },
  { value: 'et',    label: 'Estonian (et)' },
  { value: 'fi',    label: 'Finnish (fi)' },
  { value: 'fr',    label: 'French (fr)' },
  { value: 'gl',    label: 'Galician (gl)' },
  { value: 'ka',    label: 'Georgian (ka)' },
  { value: 'de',    label: 'German (de)' },
  { value: 'el',    label: 'Greek (el)' },
  { value: 'he',    label: 'Hebrew (he)' },
  { value: 'hi',    label: 'Hindi (hi)' },
  { value: 'hu',    label: 'Hungarian (hu)' },
  { value: 'is',    label: 'Icelandic (is)' },
  { value: 'id',    label: 'Indonesian (id)' },
  { value: 'ga',    label: 'Irish (ga)' },
  { value: 'it',    label: 'Italian (it)' },
  { value: 'ja',    label: 'Japanese (ja)' },
  { value: 'kk',    label: 'Kazakh (kk)' },
  { value: 'ko',    label: 'Korean (ko)' },
  { value: 'lv',    label: 'Latvian (lv)' },
  { value: 'lt',    label: 'Lithuanian (lt)' },
  { value: 'mk',    label: 'Macedonian (mk)' },
  { value: 'ms',    label: 'Malay (ms)' },
  { value: 'mt',    label: 'Maltese (mt)' },
  { value: 'no',    label: 'Norwegian (no)' },
  { value: 'fa',    label: 'Persian (fa)' },
  { value: 'pl',    label: 'Polish (pl)' },
  { value: 'pt',    label: 'Portuguese (pt)' },
  { value: 'pt-br', label: 'Portuguese Brazil (pt-br)' },
  { value: 'ro',    label: 'Romanian (ro)' },
  { value: 'ru',    label: 'Russian (ru)' },
  { value: 'sr',    label: 'Serbian (sr)' },
  { value: 'sk',    label: 'Slovak (sk)' },
  { value: 'sl',    label: 'Slovenian (sl)' },
  { value: 'es',    label: 'Spanish (es)' },
  { value: 'sw',    label: 'Swahili (sw)' },
  { value: 'sv',    label: 'Swedish (sv)' },
  { value: 'tl',    label: 'Tagalog (tl)' },
  { value: 'th',    label: 'Thai (th)' },
  { value: 'tr',    label: 'Turkish (tr)' },
  { value: 'uk',    label: 'Ukrainian (uk)' },
  { value: 'ur',    label: 'Urdu (ur)' },
  { value: 'uz',    label: 'Uzbek (uz)' },
  { value: 'vi',    label: 'Vietnamese (vi)' },
  { value: 'cy',    label: 'Welsh (cy)' },
]

const LOCALE_FLAG_COUNTRIES = {
  af: 'za', sq: 'al', ar: 'sa', hy: 'am', az: 'az', eu: 'es', be: 'by',
  bs: 'ba', bg: 'bg', ca: 'es', zh: 'cn', 'zh-tw': 'tw', hr: 'hr', cs: 'cz',
  da: 'dk', nl: 'nl', en: 'gb', et: 'ee', fi: 'fi', fr: 'fr', gl: 'es',
  ka: 'ge', de: 'de', el: 'gr', he: 'il', hi: 'in', hu: 'hu', is: 'is',
  id: 'id', ga: 'ie', it: 'it', ja: 'jp', kk: 'kz', ko: 'kr', lv: 'lv',
  lt: 'lt', mk: 'mk', ms: 'my', mt: 'mt', no: 'no', fa: 'ir', pl: 'pl',
  pt: 'pt', 'pt-br': 'br', ro: 'ro', ru: 'ru', sr: 'rs', sk: 'sk', sl: 'si',
  es: 'es', sw: 'tz', sv: 'se', tl: 'ph', th: 'th', tr: 'tr', uk: 'ua',
  ur: 'pk', uz: 'uz', vi: 'vn', cy: 'gb-wls',
}

export function localeLabel(locale) {
  return LOCALE_OPTIONS.find((option) => option.value === locale)?.label ?? locale
}

export function localeName(locale) {
  const label = localeLabel(locale)
  const suffix = ` (${locale})`
  return label.toLowerCase().endsWith(suffix.toLowerCase())
    ? label.slice(0, -suffix.length)
    : label
}

export function localeFlagCountry(locale) {
  return LOCALE_FLAG_COUNTRIES[String(locale).toLowerCase()] ?? ''
}
