<template>
  <h1>Hello {{ name }}</h1>
  <div v-for="(month, i) in months" :key="i">
    {{i+1}} {{month}}
  </div>

</template>

<script>
function get_langcode() {
  let langcode = document.querySelector("html").getAttribute("lang")
      ? document.querySelector("html").getAttribute("lang").replace("_", "-")
      : "en"; // get the language attribute from the HTML or default to english if it doesn't exists.
  return langcode;
}
function get_days_of_the_week_initials(format = 'narrow'){
  let langcode = get_langcode();
  let now = new Date()
  const int_format = new Intl.DateTimeFormat(langcode, {weekday:format}).format;
  return [...Array(7).keys()].map((day) => int_format(new Date().getTime() - (now.getDay() - day) * 86400000));
}
function get_months_labels(format = 'long'){
  let langcode = get_langcode();
  const int_format = new Intl.DateTimeFormat(langcode, {month:format}).format;
  return [...Array(12).keys()].map((month) => int_format(new Date( Date.UTC(2021, month+1, 1))));
}

export default {
  name: 'Calendar',
  data(){
    return {
      name: "bob",
      months: get_months_labels(),
      weekdays: get_days_of_the_week_initials(),
      date: ""
    }
  }
}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
h3 {
  margin: 40px 0 0;
}
ul {
  list-style-type: none;
  padding: 0;
}
li {
  display: inline-block;
  margin: 0 10px;
}
a {
  color: #42b983;
}
</style>
