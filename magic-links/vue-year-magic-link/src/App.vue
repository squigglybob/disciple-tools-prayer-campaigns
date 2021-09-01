<template>
  <Calendar/>
<!--  <DatePicker></DatePicker>-->
  <SignUp></SignUp>
</template>

<script>
import Calendar from './components/Calendar.vue'
// import DatePicker from './components/DatePicker.vue'
import SignUp from './components/SignUp.vue'

export default {
  name: 'App',
  components: {
    Calendar,
    // DatePicker,
    SignUp
  }
}
</script>

<style>
#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
  margin-top: 60px;

  display: flex;
  flex-direction: column;
  align-items: center;
}

button {
  margin: 1em;
  padding: .5em;
  background-color: dodgerblue;
  border: 1px solid white;
  border-radius: 5px;
  color: white;
  /*width:min-content;*/
}
input {
  padding: .5em;
}
</style>
