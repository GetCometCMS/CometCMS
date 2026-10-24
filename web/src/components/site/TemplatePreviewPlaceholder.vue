<template>
  <span class="generic-preview" aria-hidden="true">
    <span v-if="part === 'header'" class="preview-header">
      <span class="preview-logo"></span>
      <span class="preview-nav"><span></span><span></span><span></span></span>
      <span class="preview-action"></span>
    </span>
    <span v-else-if="part === 'content'" class="preview-content">
      <span class="preview-hero">
        <svg viewBox="0 0 160 90" fill="currentColor" focusable="false">
          <circle cx="111" cy="22" r="11" />
          <path d="M24 66 57 32a7 7 0 0 1 10 0l24 25 11-11a7 7 0 0 1 10 0l22 24H27a3 3 0 0 1-3-4Z" />
        </svg>
      </span>
      <span class="preview-copy"><span></span><span></span><span></span></span>
      <span class="preview-cards">
        <span v-for="card in 3" :key="card" class="preview-card">
          <span class="preview-card__image"><span></span></span>
          <span class="preview-card__line"></span>
          <span class="preview-card__line preview-card__line--short"></span>
        </span>
      </span>
    </span>
    <span v-else class="preview-footer">
      <span class="preview-social"><span></span><span></span><span></span></span>
      <span class="preview-nav"><span></span><span></span><span></span></span>
      <span class="preview-social"><span></span><span></span><span></span></span>
    </span>
  </span>
</template>

<script setup>
defineProps({ part: { type: String, required: true } });
</script>

<style scoped>
.generic-preview { display: block; }
.preview-header,
.preview-footer {
  display: flex;
  align-items: center;
  gap: 24px;
  padding: 28px 20px;
}

.preview-logo {
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  border-radius: 50%;
  background: rgb(var(--part-color) / 0.3);
}

.preview-nav {
  display: flex;
  flex: 1;
  gap: 14px;
}

.preview-nav > span,
.preview-copy > span,
.preview-card__line {
  display: block;
  height: 8px;
  border-radius: 5px;
  background: currentColor;
}

.preview-nav > span {
  width: 22%;
  max-width: 48px;
}

.preview-action {
  width: 22%;
  height: 23px;
  border-radius: 5px;
  background: rgb(var(--part-color) / 0.2);
}

.preview-content {
  display: grid;
  gap: 16px;
  padding: 22px 28px 36px;
}

.preview-hero {
  display: flex;
  height: 88px;
  justify-content: center;
  border-radius: 7px;
  background: currentColor;
}

.preview-hero svg {
  width: 140px;
  color: rgb(var(--color-slate-400) / 0.4);
}

.preview-copy {
  display: grid;
  gap: 8px;
}

.preview-copy > span:nth-child(2) { width: 91%; }
.preview-copy > span:nth-child(3) { width: 76%; }

.preview-cards {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.preview-card {
  display: grid;
  gap: 8px;
}

.preview-card__image {
  display: grid;
  height: 50px;
  place-items: center;
  border-radius: 5px;
  background: currentColor;
}

.preview-card__image > span {
  width: 12px;
  height: 12px;
  border-radius: 3px;
  background: currentColor;
  transform: rotate(45deg);
}

.preview-card__line { width: 80%; }
.preview-card__line--short { width: 56%; }

.preview-footer {
  gap: 22px;
  padding-top: 30px;
}

.preview-social {
  display: flex;
  gap: 9px;
}

.preview-social > span {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: currentColor;
}

@media (max-width: 480px) {
  .preview-header, .preview-footer { gap: 12px; padding-inline: 14px; }
  .preview-content { padding-inline: 18px; }
  .preview-cards { gap: 10px; }
  .preview-social { gap: 5px; }
  .preview-social > span { width: 8px; height: 8px; }
  .preview-nav { gap: 8px; }
}
</style>
