# Reglas de R8 para SIGMA. Flutter agrega las suyas; aqui se protege el codigo de los plugins.
# Los plugins se llaman desde Dart por canales y Pigeon: R8 no debe renombrar ni cambiar sus metodos.
-keep class io.flutter.plugins.** { *; }
-keep class dev.flutter.pigeon.** { *; }
-keep class com.it_nomads.fluttersecurestorage.** { *; }
-keep class io.flutter.plugins.sharedpreferences.** { *; }
-keep class androidx.datastore.** { *; }
# Kotlin y corrutinas (shared_preferences usa funciones suspend).
-keep class kotlin.coroutines.** { *; }
-keep class kotlinx.coroutines.** { *; }
-keepclassmembers class kotlin.Metadata { *; }
-keepattributes Signature, InnerClasses, EnclosingMethod, *Annotation*
-dontwarn com.google.android.play.core.**
-dontwarn com.google.errorprone.annotations.**
-dontwarn javax.annotation.**
# Sin optimizaciones agresivas: solo se elimina el codigo sin uso (reduccion), no se reescribe.
-dontoptimize
