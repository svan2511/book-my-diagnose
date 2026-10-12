<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Your admin sign-in code</title>
</head>
<body style="margin:0;padding:0;background-color:#f8f9ff;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5eeff;">
          <tr>
            <td style="background:#002532;padding:24px 32px;">
              <div style="color:#ffffff;font-size:18px;font-weight:bold;">Book My Diagnose</div>
              <div style="color:#a3cde0;font-size:12px;letter-spacing:1px;margin-top:4px;">ADMIN OPERATIONS</div>
            </td>
          </tr>
          <tr>
            <td style="padding:32px;">
              <p style="margin:0 0 8px;font-size:16px;color:#0b1c30;">Hello {{ $name }},</p>
              <p style="margin:0 0 20px;font-size:14px;line-height:22px;color:#41484b;">
                Use this one-time code to finish signing in to the admin dashboard. It expires in {{ $minutes }} minutes.
              </p>
              <div style="text-align:center;margin:0 0 20px;">
                <span style="display:inline-block;font-size:34px;font-weight:bold;letter-spacing:10px;color:#002532;background:#eff4ff;border:1px dashed #006591;border-radius:12px;padding:14px 20px 14px 30px;">{{ $code }}</span>
              </div>
              <p style="margin:0;font-size:13px;line-height:20px;color:#71787c;">
                Never share this code with anyone. If you did not request it, change your password immediately and contact support.
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px;background:#f8f9ff;font-size:12px;color:#71787c;">
              NABL &amp; ICMR accredited network &bull; 45+ cities
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
