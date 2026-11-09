import 'package:flutter_application_1/pantallas/escaner_qr.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('extrae el código del ascensor del QR de SIGMA', () {
    expect(codigoDeQr('https://sigma.proyectoscampus.top/reportar/vla8v8amypew6mvamjpy'), 'vla8v8amypew6mvamjpy');
    expect(codigoDeQr('vla8v8amypew6mvamjpy'), 'vla8v8amypew6mvamjpy');
    expect(codigoDeQr('https://otro-sitio.com/promocion'), isNull);
    expect(codigoDeQr(''), isNull);
  });
}
